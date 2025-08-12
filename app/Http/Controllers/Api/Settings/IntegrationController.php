<?php
namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Models\Integration;
use App\Services\IntegrationService;
use Illuminate\Http\Request;

class IntegrationController extends Controller
{
    public function index()
    {
        return Integration::all();
    }
    
    public function update(Request $request, Integration $integration, IntegrationService $integrationService)
    {
        $validated = $request->validate([
            'credentials.secret' => 'required|string',
            'credentials.webhook_key' => 'required|string',
            'is_active' => 'boolean',
        ]);

        $integration->update($validated);
        
        // 🔹 ¡Paso clave! Limpiar la caché para que se usen las nuevas credenciales.
        $integrationService->clearCache($integration->provider);

        return response()->json($integration);
    }

    public function updateName(Request $request, Integration $integration)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $integration->update(['name' => $validated['name']]);

        return response()->json(['message' => 'Nombre de la integración actualizado.']);
    }
}