<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http; // ✅ Usaremos el cliente HTTP de Laravel
use Illuminate\Support\Facades\Log;
use App\Models\Crm\ExternalLead;

class ProcessFacebookLeadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected array $leadData;

    public function __construct(array $leadData)
    {
        $this->leadData = $leadData;
    }

    public function handle(): void
    {
        $leadId = $this->leadData['leadgen_id'];
        
        // Obtenemos el token de acceso a la página desde la tabla de Integraciones.
        // Esto es más seguro que un placeholder.
        $integration = \App\Models\Integration::where('provider', 'facebook')->where('is_active', true)->first();
        $accessToken = $integration->credentials['page_access_token'] ?? null;

        if (!$accessToken) {
            Log::error("No hay un token de acceso a la página de Facebook configurado en las integraciones para procesar el lead ID: {$leadId}");
            // Opcional: Podríamos reintentar el job más tarde si el token se está actualizando.
            // $this->release(300); // Reintentar en 5 minutos
            return;
        }

        // 1. Llamar a la API de Facebook para obtener los detalles del lead
        $response = Http::get("https://graph.facebook.com/" . config('services.facebook.graph_version') . "/{$leadId}", [
            'access_token' => $accessToken
        ]);
        
        if ($response->failed()) {
            Log::error("Error al obtener datos del lead de Facebook ID: {$leadId}", $response->json());
            return;
        }

        $leadDetails = $response->json();
        $payload = [];

        // 2. Mapear los campos de Facebook a nuestro formato de payload estándar
        foreach ($leadDetails['field_data'] as $field) {
            // Normalizamos el nombre del campo a snake_case para consistencia (ej. "full_name")
            $fieldName = strtolower(str_replace(' ', '_', $field['name']));
            
            $fieldValue = count($field['values']) > 1 ? implode(', ', $field['values']) : $field['values'][0];
            
            $payload[$fieldName] = $fieldValue;
        }
        
        // Mapeos comunes de Facebook a nuestro sistema
        if (isset($payload['full_name']) && !isset($payload['first_name'])) {
            $parts = explode(' ', $payload['full_name'], 2);
            $payload['first_name'] = $parts[0];
            $payload['last_name'] = $parts[1] ?? '';
        }

        // 3. Crear el registro en nuestra tabla de leads externos
        $newLead = ExternalLead::create([
            'source'         => 'facebook',
            'payload'        => $payload,
            'status'         => 'pendiente',
            'received_at'    => now(),
        ]);

        // ✅ Inmediatamente después de crearlo, despachamos el job principal que lo procesará.
        ProcessLeadJob::dispatch($newLead);
        
        Log::info("Lead de Facebook ID {$leadId} guardado (ExternalLead ID: {$newLead->id}) y encolado para el workflow.");
    }
}