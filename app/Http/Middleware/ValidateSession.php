<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use App\Models\UserSession;

class ValidateSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $request->bearerToken()) {
            $token = $user->currentAccessToken();

            $session = UserSession::where('user_id', $user->id)
                ->where('token_id', $token->id)
                ->whereNull('revoked_at')
                ->first();

            if (!$session) {
                /** @var \Laravel\Sanctum\PersonalAccessToken $token */
                $token->delete();
                return response()->json([
                    'message' => 'Sesión revocada o no encontrada.'
                ], 401);
            }

            // Opcional: invalidar si ha pasado tiempo de inactividad (ej: 15 minutos)
            if ($session->last_activity_at->diffInMinutes(now()) > 15) {
                $session->update(['revoked_at' => now()]);
                /** @var \Laravel\Sanctum\PersonalAccessToken $token */
                $token->delete();
                return response()->json(['message' => 'Sesión expirada por inactividad.'], 401);
            }

            $session->update(['last_activity_at' => now()]);
        }

        return $next($request);
    }
}
