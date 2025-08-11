<?php

namespace App\Http\Controllers\Api\CRM;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessFacebookLeadJob; // Crearemos este Job a continuación
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FacebookWebhookController extends Controller
{
    /**
     * Verifica la suscripción al webhook de Facebook (GET request).
     */
    public function verify(Request $request)
    {
        $verifyToken = config('services.facebook.verify_token');
        
        if ($request->query('hub_mode') === 'subscribe' && $request->query('hub_verify_token') === $verifyToken) {
            Log::info('Webhook de Facebook verificado correctamente.');
            return response($request->query('hub_challenge'), 200);
        }

        Log::warning('Intento de verificación de webhook de Facebook fallido.');
        return response('Verificación fallida.', 403);
    }

    /**
     * Maneja los eventos entrantes del webhook (nuevos leads).
     */
    public function handle(Request $request)
    {
        $entries = $request->input('entry', []);

        foreach ($entries as $entry) {
            foreach ($entry['changes'] as $change) {
                // Nos aseguramos de que sea una notificación de un nuevo lead
                if ($change['field'] === 'leadgen') {
                    $leadData = $change['value'];
                    // Ponemos el lead en una cola para ser procesado en segundo plano
                    ProcessFacebookLeadJob::dispatch($leadData);
                }
            }
        }
        
        // Respondemos inmediatamente a Facebook para que no reintente
        return response('Evento recibido.', 200);
    }
}