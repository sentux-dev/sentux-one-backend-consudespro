<?php

namespace App\Imports;

use App\Models\Crm\ExternalLead;
use App\Models\Crm\LeadImport;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\BeforeImport;

class LeadsImport implements ToModel, WithHeadingRow, WithBatchInserts, WithChunkReading, WithEvents
{
    protected array $mappings;
    private int $leadsCreated = 0;
    protected LeadImport $leadImport;


    public function __construct(array $mappings, LeadImport $leadImport)
    {
        $this->mappings = $mappings;
        $this->leadImport = $leadImport;
    }

     public function registerEvents(): array
    {
        return [
            BeforeImport::class => function (BeforeImport $event) {
                // Obtenemos el lector del archivo y la hoja activa
                $totalRows = $event->getReader()->getTotalRows();
                
                // La primera clave del array es el nombre de la hoja
                $sheetName = array_key_first($totalRows);

                // Restamos 1 para no contar la fila de encabezado
                $rowCount = $totalRows[$sheetName] - 1;

                // Actualizamos el registro del lote de importación con el total
                $this->leadImport->update(['total_rows' => $rowCount]);
            },
        ];
    }

    public function model(array $row)
    {
        // 🔹 Lógica de validación mejorada
        $email = $this->getMappedValue('email', $row);
        $phone = $this->getMappedValue('phone', $row);

        $hasValidEmail = !empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL);
        $hasValidPhone = !empty(trim((string)$phone));

        if (!$hasValidEmail && !$hasValidPhone) {
            return null; // Ignorar fila si no tiene email ni teléfono
        }
        
        $this->leadsCreated++;

        return new ExternalLead([
            'lead_import_id' => $this->leadImport->id, // 🔹 Asigna el ID del lote
            'source'         => 'csv_import',
            'payload'        => $this->mapRow($row),
            'status'         => 'pendiente',
            'received_at'    => now(),
        ]);
    }

    /**
     * Obtiene un valor para un campo del sistema, ya sea de una columna del archivo o de un valor estático.
     */
    private function getMappedValue(string $systemField, array $row)
    {
        $mapping = $this->mappings[$systemField] ?? null;
        if (!$mapping) return null;

        if ($mapping['type'] === 'static') {
            return $mapping['value'];
        }

        if ($mapping['type'] === 'column' && !empty($mapping['value'])) {
            $columnName = strtolower(str_replace(' ', '_', $mapping['value']));
            return $row[$columnName] ?? null;
        }
        
        return null;
    }
    
    /**
     * Construye el payload final para el lead.
     */
    protected function mapRow(array $row): array
    {
        $payload = [];
        foreach ($this->mappings as $systemField => $mapping) {
            $value = $this->getMappedValue($systemField, $row);
            if ($value !== null) {
                $payload[$systemField] = $value;
            }
        }

        // Renombramos el campo de payload para los campos personalizados
        if (isset($payload['_custom_fields'])) {
            foreach ($payload['_custom_fields'] as $key => $val) {
                $payload[$key] = $val;
            }
            unset($payload['_custom_fields']);
        }
        
        $payload['_original_row'] = $row;
        return $payload;
    }
    
    public function getLeadsCreatedCount(): int { return $this->leadsCreated; }
    public function batchSize(): int { return 200; }
    public function chunkSize(): int { return 200; }
}