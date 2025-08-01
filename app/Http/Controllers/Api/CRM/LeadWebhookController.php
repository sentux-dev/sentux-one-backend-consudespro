<?php
namespace App\Http\Controllers\Api\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\ExternalLead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Jobs\ProcessLeadJob;

class LeadWebhookController extends Controller
{
    /**
     * Punto de entrada universal para leads externos.
     */
    public function ingress(Request $request, string $source)
    {
        // Validación simple: ¿es una fuente conocida?
        $allowedSources = ['facebook', 'tiktok', 'website', 'landingpage'];
        if (!in_array($source, $allowedSources)) {
            Log::warning('Intento de webhook desde fuente desconocida:', ['source' => $source]);
            return response()->json(['message' => 'Fuente no válida.'], 400);
        }

        try {
            $lead = ExternalLead::create([
                'source' => $source,
                'payload' => $request->all(),
                'status' => 'pendiente',
                'received_at' => now(),
            ]);

             // 🔹 --- Despachar el Job a la cola --- 🔹
            ProcessLeadJob::dispatch($lead);

            return response()->json(['message' => 'Lead recibido con éxito.'], 201);
        } catch (\Exception $e) {
            Log::error('Error al procesar webhook de lead:', [
                'source' => $source, 
                'error' => $e->getMessage(),
                'payload' => $request->all()
            ]);
            return response()->json(['message' => 'Error interno del servidor.'], 500);
        }
    }
}