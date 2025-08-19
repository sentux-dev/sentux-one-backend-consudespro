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

    public function store(Request $request)
    {
        $validated = $request->validate([
            'provider' => 'required|string|in:db_import,facebook,mandrill',
            'name' => 'required|string|max:255',
        ]);

        $integration = Integration::create([
            'provider' => $validated['provider'],
            'name' => $validated['name'],
            'credentials' => [],
            'is_active' => false,
        ]);

        return response()->json($integration, 201);
    }
    
    public function update(Request $request, Integration $integration, IntegrationService $integrationService)
    {
        // ✅ 1. Validación más flexible y correcta
        $validated = $request->validate([
            'is_active' => 'required|boolean',
            'credentials' => 'required|array', // Validamos que 'credentials' sea un objeto/array
        ]);

        // ✅ 2. Asignación directa de las propiedades al modelo
        $integration->is_active = $validated['is_active'];
        $integration->credentials = $validated['credentials']; // Asignamos el objeto de credenciales completo
        
        // ✅ 3. Guardamos los cambios
        $integration->save();
        
        // 4. Limpiamos la caché para que la aplicación use las nuevas credenciales inmediatamente.
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