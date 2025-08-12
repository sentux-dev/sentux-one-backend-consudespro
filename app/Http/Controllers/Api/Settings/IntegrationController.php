<?php
namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Models\Integration;
use App\Models\Log;
use App\Services\IntegrationService;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;

class IntegrationController extends Controller
{
    use AuthorizesRequests;
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

    public function destroy(Integration $integration)
    {
        // CAPA 1: AUTORIZACIÓN
        // Esta línea ejecuta la política. Si el usuario no tiene el permiso 'integrations.delete',
        // Laravel detendrá la ejecución y devolverá un error 403 (Prohibido) automáticamente.
        $this->authorize('delete', $integration);

        // CAPA 2: LÓGICA DE NEGOCIO
        if ($integration->provider !== 'facebook') {
            return response()->json(['message' => 'Este endpoint solo elimina integraciones de Facebook.'], 400);
        }
        
        // CAPA 3: AUDITORÍA
        // Registramos quién está haciendo la eliminación ANTES de que ocurra.
        Log::create([
            'user_id' => Auth::id(),
            'action' => 'delete_integration',
            'entity_type' => Integration::class,
            'entity_id' => $integration->id,
            'changes' => $integration->toArray() // Guardamos una copia de lo que se va a eliminar
        ]);

        // CAPA 4: ELIMINACIÓN PERMANENTE
        $integration->delete();

        return response()->json(['message' => 'Integración eliminada permanentemente.'], 200);
    }
}