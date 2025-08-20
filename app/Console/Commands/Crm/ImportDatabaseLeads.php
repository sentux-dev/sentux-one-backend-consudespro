<?php

namespace App\Console\Commands\Crm;

use App\Jobs\ProcessLeadJob;
use Illuminate\Console\Command;
use App\Models\Integration;
use App\Models\Crm\ExternalLead;
use App\Services\Crm\DatabaseConnectorService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Arr;

class ImportDatabaseLeads extends Command
{
    protected $signature = 'crm:import-database-leads';
    protected $description = 'Importa nuevos leads desde las bases de datos externas configuradas.';

    protected $connectorService;

    public function __construct(DatabaseConnectorService $connectorService)
    {
        parent::__construct();
        $this->connectorService = $connectorService;
    }

    public function handle()
    {
        $this->info('Iniciando la importación de leads desde bases de datos externas...');

        $integrations = Integration::where('provider', 'db_import')
            ->where('is_active', true)
            ->get();

        if ($integrations->isEmpty()) {
            $this->info('No hay integraciones de bases de datos activas para procesar.');
            return 0;
        }

        foreach ($integrations as $integration) {
            $this->line("-> Procesando integración: {$integration->name}");
            $config = $integration->credentials;
            $tableName = $config['table'] ?? null;
            $mappings = $config['mappings'] ?? [];
            $cursorColumn = 'id'; // Asumimos 'id' como columna de cursor por defecto
            $lastCursorValue = $integration->sync_cursors[$tableName] ?? 0;

            if (!$tableName || empty($mappings)) {
                $this->warn("   -> Omitiendo: La integración no tiene una tabla o mapeos configurados.");
                continue;
            }

            try {
                $connection = $this->connectorService->connect($integration);
                $this->info("   -> Conexión exitosa a la base de datos '{$config['database']}'.");

                $newRecords = $connection->table($tableName)
                    ->where($cursorColumn, '>', $lastCursorValue)
                    ->orderBy($cursorColumn, 'asc')
                    ->limit(100)
                    ->get();

                if ($newRecords->isEmpty()) {
                    $this->info("   -> No se encontraron nuevos leads en la tabla '{$tableName}'.");
                    continue;
                }

                $this->info("   -> Se encontraron {$newRecords->count()} nuevos leads. Importando...");
                $lastImportedId = $lastCursorValue;

                foreach ($newRecords as $record) {
                    $recordArray = (array)$record;
                    $payload = [];

                    foreach ($mappings as $mapping) {
                        $value = null;

                        // Verifica si es una regla de tipo estático
                        if (isset($mapping['type']) && $mapping['type'] === 'static') {
                            $value = $mapping['staticValue'] ?? null;
                        } 
                        // Si no, es una regla normal de mapeo de columna
                        elseif (!empty($mapping['source'])) {
                            $sourceKey = $mapping['source'];
                            
                            if (str_contains($sourceKey, '.')) {
                                list($jsonColumn, $nestedKey) = explode('.', $sourceKey, 2);
                                if (isset($recordArray[$jsonColumn])) {
                                    $columnContent = $recordArray[$jsonColumn];
                                    $jsonData = is_array($columnContent) ? $columnContent : json_decode($columnContent, true);
                                    if (is_array($jsonData)) {
                                        $value = Arr::get($jsonData, $nestedKey);
                                    }
                                }
                            } else {
                                $value = Arr::get($recordArray, $sourceKey);
                            }
                        }

                        if (empty($mapping['destination'])) continue;

                        $destinationKey = ($mapping['destination'] === 'payload_only')
                            ? ($mapping['payloadKey'] ?? ($mapping['source'] ?? 'static_value'))
                            : $mapping['destination'];
                        
                        Arr::set($payload, $destinationKey, $value);
                    }

                    $externalLead =ExternalLead::create([
                        'integration_id' => $integration->id,
                        'source'         => 'db_import',
                        'external_id'    => $recordArray[$cursorColumn],
                        'payload'        => $payload,
                        'status'         => 'pendiente',
                        'received_at'    => now(),
                    ]);

                    ProcessLeadJob::dispatch($externalLead);

                    
                    $lastImportedId = $recordArray[$cursorColumn];
                }

                $cursors = $integration->sync_cursors ?? [];
                $cursors[$tableName] = $lastImportedId;
                $integration->sync_cursors = $cursors;
                $integration->save();
                
                $this->info("   -> Importación completada. Último ID procesado: {$lastImportedId}");

            } catch (\Throwable $e) {
                $this->error("   -> ERROR al procesar la integración {$integration->name}: " . $e->getMessage());
                Log::error("Fallo en importación de BD", ['integration_id' => $integration->id, 'error' => $e->getMessage()]);
            }
        }

        $this->info('Importación finalizada.');
        return 0;
    }
}