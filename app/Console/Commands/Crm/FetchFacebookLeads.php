<?php
namespace App\Console\Commands\Crm;

use Illuminate\Console\Command;
use App\Models\Integration;
use App\Models\Crm\ExternalLead;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;

class FetchFacebookLeads extends Command
{
    protected $signature = 'crm:fetch-facebook-leads';
    protected $description = 'Consulta la API de Facebook para obtener nuevos leads de los formularios suscritos.';

    public function handle()
    {
        $this->info('Iniciando la búsqueda de nuevos leads de Facebook...');

        $integrations = Integration::where('provider', 'facebook')
            ->where('is_active', true)
            ->whereJsonLength('credentials->form_ids', '>', 0)
            ->get();

        foreach ($integrations as $integration) {
            $this->line("Procesando integración: {$integration->name}");
            $credentials = $integration->credentials;
            $pageAccessToken = $credentials['page_access_token'] ?? null;
            $formIds = $credentials['form_ids'] ?? [];
            $cursors = $integration->sync_cursors ?? [];

            if (!$pageAccessToken || empty($formIds)) continue;

            foreach ($formIds as $formId) {
                try {
                    $lastCursor = $cursors[$formId] ?? null;

                    // 1. Construir la petición a la API
                    $requestParams = ['access_token' => $pageAccessToken, 'limit' => 25];
                    if ($lastCursor) {
                        $requestParams['after'] = $lastCursor;
                    }

                    $response = Http::get("https://graph.facebook.com/" . config('services.facebook.graph_version') . "/{$formId}/leads", $requestParams);

                    if ($response->failed()) {
                        Log::error("Error al obtener leads para el formulario {$formId}", $response->json());
                        continue;
                    }

                    $leads = $response->json()['data'] ?? [];
                    if (empty($leads)) continue;
                    
                    $this->info("-> Formulario {$formId}: " . count($leads) . " leads nuevos encontrados.");
                    
                    foreach ($leads as $lead) {
                        $this->processLead($lead, $integration);
                    }
                    
                    // 2. Guardar el nuevo cursor para la próxima ejecución
                    $newCursor = $response->json()['paging']['cursors']['after'] ?? null;
                    if ($newCursor) {
                        $cursors[$formId] = $newCursor;
                    }

                } catch (\Exception $e) {
                    Log::error("Excepción al procesar el formulario {$formId}", ['message' => $e->getMessage()]);
                }
            }
            
            // 3. Actualizar la base de datos con los últimos cursores de esta integración
            $integration->sync_cursors = $cursors;
            $integration->save();
        }

        $this->info('Búsqueda de leads de Facebook finalizada.');
        return 0;
    }

    private function processLead(array $leadData, Integration $integration)
    {
        $payload = [];
        foreach ($leadData['field_data'] as $field) {
            $fieldName = strtolower(str_replace(' ', '_', $field['name']));
            $fieldValue = $field['values'][0] ?? null;
            $payload[$fieldName] = $fieldValue;
        }
        
        if (isset($payload['full_name']) && !isset($payload['first_name'])) {
            $parts = explode(' ', $payload['full_name'], 2);
            $payload['first_name'] = $parts[0];
            $payload['last_name'] = $parts[1] ?? '';
        }

        // Usamos firstOrCreate para evitar duplicados si un lead se procesa más de una vez
        ExternalLead::firstOrCreate(
            ['source' => 'facebook', 'external_id' => $leadData['id']],
            [
                'payload' => $payload,
                'status' => 'pendiente',
                'integration_id' => $integration->id,
                'received_at' => Carbon::parse($leadData['created_time']),
            ]
        );
    }
}