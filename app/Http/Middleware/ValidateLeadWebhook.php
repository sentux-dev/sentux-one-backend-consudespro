<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Crm\LeadSource;

class ValidateLeadWebhook
{
    public function handle(Request $request, Closure $next): Response
    {
        $sourceKey = $request->route('source');
        $apiKey = $request->header('X-API-KEY');

        if (!$apiKey) {
            return response()->json(['message' => 'API Key faltante.'], 401);
        }

        $source = LeadSource::where('source_key', $sourceKey)->where('is_active', true)->first();

        if (!$source) {
            return response()->json(['message' => 'Fuente no encontrada o inactiva.'], 404);
        }

        if (!hash_equals($source->api_key, $apiKey)) {
            return response()->json(['message' => 'API Key inválida.'], 401);
        }

        // 🔹 --- LÓGICA DE DOMINIO CORREGIDA Y MÁS ESTRICTA --- 🔹
        // Si el array de dominios permitidos NO está vacío, la validación es obligatoria.
        if (!empty($source->allowed_domains)) {
            $originHeader = $request->header('Origin');

            // 1. Exigir que el encabezado 'Origin' exista.
            if (!$originHeader) {
                return response()->json(['message' => 'Not Authorized'], 401);
            }

            // 2. Extraer solo el host (ej: 'www.midominio.com') de la URL completa.
            $originHost = parse_url($originHeader, PHP_URL_HOST);

            // 3. Validar que el host extraído esté en la lista de dominios permitidos.
            if (!$originHost || !in_array($originHost, $source->allowed_domains)) {
                return response()->json(['message' => 'Not Authorized'], 401);
            }
        }

        return $next($request);
    }
}