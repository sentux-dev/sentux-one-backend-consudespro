<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\Session\UserSessionFilterRequest;
use App\Http\Resources\User\Session\UserSessionResource;
use App\Models\UserSession;
use Illuminate\Http\Request;
use App\Services\User\UserSessionQueryService;
use App\Services\UserSessionService;

class SessionController extends Controller
{
    protected $sessionService;

    public function __construct(UserSessionQueryService $sessionService)
    {
        $this->sessionService = $sessionService;
    }

    public function index(UserSessionFilterRequest $request, UserSessionQueryService $service)
    {
        $filters = $request->validatedWithDefaults();

        $paginator = $service->getUserSessions(
            auth()->id(),
            $filters['order_by'],
            $filters['order_dir'],
            $filters['per_page'],
            $filters['page'],         // ← AÑADIR ESTO
            $filters['search']        // ← Y asegúrate de que lo soporta el servicio
        );

        return UserSessionResource::collection($paginator)->response();
    }

    public function destroy(int $id)
    {
        $session = UserSession::where('user_id', auth()->id())
            ->where('id', $id)
            ->whereNull('revoked_at')
            ->firstOrFail();

        $session->update(['revoked_at' => now()]);

        // Opcional: si quieres cerrar el token asociado a esa sesión
        if ($session->token_id) {
            $token = \Laravel\Sanctum\PersonalAccessToken::find($session->token_id);
            $token?->delete(); // Cierra la sesión
        }

        return response()->json(['message' => 'Sesión revocada.']);
    }

}
