<?php

namespace App\Http\Controllers\Api\CRM;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Integration; // Asumiendo que tienes este modelo

class FacebookIntegrationController extends Controller
{
    /**
     * Genera y devuelve la URL de autorización de Facebook.
     */
    public function getAuthUrl(Request $request)
    {
        $url = "https://www.facebook.com/" . config('services.facebook.graph_version') . "/dialog/oauth";
        
        $params = [
            'client_id' => config('services.facebook.app_id'),
            'redirect_uri' => config('services.facebook.redirect_uri'),
            'scope' => 'pages_show_list,pages_manage_ads,leads_retrieval', // Permisos necesarios
            'response_type' => 'code',
            'state' => csrf_token(), // Medida de seguridad
        ];

        return response()->json([
            'auth_url' => $url . '?' . http_build_query($params),
        ]);
    }

    /**
     * Maneja el callback de Facebook después de la autorización.
     */
    public function handleCallback(Request $request)
    {
        if ($request->has('error')) {
            Log::error('Error en el callback de Facebook', ['error' => $request->error_description]);
            return response()->view('auth.facebook-callback', ['success' => false, 'message' => 'Autorización cancelada.']);
        }

        // 1. Intercambiar el código por un token de acceso de corta duración
        $response = Http::get('https://graph.facebook.com/' . config('services.facebook.graph_version') . '/oauth/access_token', [
            'client_id' => config('services.facebook.app_id'),
            'client_secret' => config('services.facebook.app_secret'),
            'redirect_uri' => config('services.facebook.redirect_uri'),
            'code' => $request->code,
        ]);

        if ($response->failed()) {
            return response()->view('auth.facebook-callback', ['success' => false, 'message' => 'No se pudo obtener el token de acceso.']);
        }
        $accessToken = $response->json()['access_token'];

        // 2. Intercambiar el token de corta duración por uno de larga duración (60 días)
        $response = Http::get('https://graph.facebook.com/' . config('services.facebook.graph_version') . '/oauth/access_token', [
            'grant_type' => 'fb_exchange_token',
            'client_id' => config('services.facebook.app_id'),
            'client_secret' => config('services.facebook.app_secret'),
            'fb_exchange_token' => $accessToken,
        ]);

        if ($response->failed()) {
             return response()->view('auth.facebook-callback', ['success' => false, 'message' => 'No se pudo obtener el token de larga duración.']);
        }
        $longLivedToken = $response->json()['access_token'];

        // 3. Guardar el token en la configuración de la integración
        $integration = Integration::where('provider', 'facebook')->firstOrFail();
        $credentials = $integration->credentials;
        $credentials['user_access_token'] = $longLivedToken;
        $integration->credentials = $credentials;
        $integration->save();

        return response()->view('auth.facebook-callback', ['success' => true]);
    }

    /**
     * Obtiene la lista de páginas de Facebook que el usuario administra.
     */
    public function getPages(Request $request)
    {
        $integration = Integration::where('provider', 'facebook')->first();
        $token = $integration->credentials['user_access_token'] ?? null;

        if (!$token) {
            return response()->json(['message' => 'No se ha conectado una cuenta de Facebook.'], 400);
        }

        $response = Http::get("https://graph.facebook.com/me/accounts", [
            'access_token' => $token,
            'fields' => 'id,name,access_token,tasks', // Pedimos el token de acceso de la página
        ]);
        
        return response()->json($response->json()['data'] ?? []);
    }

    /**
     * Suscribe una página a los webhooks de la aplicación.
     */
    public function subscribePage(Request $request)
    {
        $validated = $request->validate([
            'page_id' => 'required|string',
            'page_access_token' => 'required|string',
        ]);
        
        $pageId = $validated['page_id'];
        $pageAccessToken = $validated['page_access_token'];

        // Guardamos el token de la página para usarlo después
        $integration = Integration::where('provider', 'facebook')->firstOrFail();
        $credentials = $integration->credentials;
        $credentials['page_id'] = $pageId;
        $credentials['page_access_token'] = $pageAccessToken;
        $integration->credentials = $credentials;
        $integration->save();
        
        // Suscribimos la página a los eventos de 'leadgen'
        $response = Http::post("https://graph.facebook.com/{$pageId}/subscribed_apps", [
            'subscribed_fields' => 'leadgen',
            'access_token' => $pageAccessToken,
        ]);
        
        if ($response->failed()) {
            return response()->json(['message' => 'No se pudo suscribir la página.'], 500);
        }

        return response()->json(['message' => 'Página suscrita a los leads correctamente.']);
    }
}