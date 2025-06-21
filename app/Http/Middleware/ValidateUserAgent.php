<?php

namespace App\Http\Middleware;

use App\Models\UserSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ValidateUserAgent
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && $request->bearerToken()) {
            $token = $user->currentAccessToken();

            if ($token) {
                $session = UserSession::where('user_id', $user->id)
                    ->where('token_id', $token->id)
                    ->whereNull('revoked_at')
                    ->latest()
                    ->first();

                if (!$session || $session->user_agent !== $request->userAgent()) {
                    return response()->json([
                        'message' => 'Dispositivo o navegador no autorizado.'
                    ], 401);
                }
            }
        }

        return $next($request);
    }
}
