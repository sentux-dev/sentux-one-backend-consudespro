<?php
namespace App\Services;

use App\Models\Integration;
use Illuminate\Support\Facades\Cache;

class IntegrationService
{
    /**
     * Obtiene las credenciales para un proveedor, usando la caché primero.
     */
    public function getCredentials(string $provider): ?array
    {
        $cacheKey = "integration.{$provider}.credentials";

        // Devuelve desde la caché si existe.
        return Cache::rememberForever($cacheKey, function () use ($provider) {
            // Si no está en caché, lo busca en la BD y lo guarda en caché para siempre.
            $integration = Integration::where('provider', $provider)->where('is_active', true)->first();
            return $integration ? $integration->credentials : null;
        });
    }

    /**
     * Limpia la caché para un proveedor. Se debe llamar al actualizar.
     */
    public function clearCache(string $provider): void
    {
        Cache::forget("integration.{$provider}.credentials");
    }
}