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

        // 1. Obtenemos todas las integraciones de FB activas.
        $allIntegrations = Integration::where('provider', 'facebook')
            ->where('is_active', true)
            ->get();

        // 2. Filtramos en PHP para manejar correctamente las credenciales encriptadas.
        $integrations = $allIntegrations->filter(function ($integration) {
            return !empty($integration->credentials['form_ids']);
        });

        if ($integrations->isEmpty()) {
            $this->info('No se encontraron integraciones de Facebook activas con formularios configurados.');
            return 0;
        }

        foreach ($integrations as $integration) {
            $this->line("Procesando integración: {$integration->name}");
            
            $credentials = $integration->credentials;
            $pageAccessToken = $credentials['page_access_token'] ?? null;
            $formIds = $credentials['form_ids'] ?? [];
            $cursors = is_array($integration->sync_cursors) ? $integration->sync_cursors : [];

            if (!$pageAccessToken || empty($formIds)) {
                Log::warning("Omitiendo integración {$integration->name} (ID: {$integration->id}) por falta de token o form_ids.");
                continue;
            }

            foreach ($formIds as $formId) {
                try {
                    $lastCursor = $cursors[$formId] ?? null;

                    $requestParams = ['access_token' => $pageAccessToken, 'limit' => 25];
                    if ($lastCursor) {
                        $requestParams['after'] = $lastCursor;
                    }

                    $apiUrl = "https://graph.facebook.com/" . config('services.facebook.graph_version') . "/{$formId}/leads";
                    $response = Http::get($apiUrl, $requestParams);

                    if ($response->failed()) {
                        Log::error("Error al obtener leads para el formulario {$formId}", $response->json());
                        continue;
                    }

                    $leads = $response->json()['data'] ?? [];
                    
                    if (!empty($leads)) {
                        $this->info("-> Formulario {$formId}: " . count($leads) . " leads nuevos encontrados.");
                        foreach ($leads as $lead) {
                            $this->processLead($lead, $integration);
                        }
                    }
                    
                    $newCursor = $response->json()['paging']['cursors']['after'] ?? null;
                    if ($newCursor) {
                        $cursors[$formId] = $newCursor;
                    }

                } catch (\Exception $e) {
                    Log::error("Excepción al procesar el formulario {$formId}", ['message' => $e->getMessage()]);
                }
            }
            
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