<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Services\UserSessionService;
use Illuminate\Container\Attributes\Log;
use Illuminate\Support\Facades\Log as FacadesLog;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:6|confirmed'
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => bcrypt($data['password']),
        ]);

        return response()->json([
            'token' => $user->createToken('orbitflow-token')->plainTextToken,
            'user' => $user,
        ]);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => ['Credenciales inválidas.']]);
        }

        // Si MFA está habilitado, no emitir token todavía
        if ($user->mfa_enabled) {
            if ($user->mfa_type === 'email') {
                $code = rand(100000, 999999);
                Cache::put("mfa_login_code_{$user->id}", $code, now()->addMinutes(10));

                Mail::raw("Tu código de acceso es: $code", function ($message) use ($user) {
                    $message->to($user->email)->subject('Código de acceso - Verificación MFA');
                });

                return response()->json([
                    'mfa_required' => true,
                    'mfa_type' => 'email'
                ]);
            }

            if ($user->mfa_type === 'app') {
                return response()->json([
                    'mfa_required' => true,
                    'mfa_type' => 'app'
                ]);
            }
        }

        return $this->completeLogin($user);
    }

    protected function completeLogin($user)
    {
        $accessToken = $user->createToken('auth_token');
        $token = $accessToken->plainTextToken;
        $tokenId = $accessToken->accessToken->id;

        app(UserSessionService::class)->createSession($user, $tokenId);

        $user->last_login_at = now();
        $user->save();

        // formato de respuesta
        $user->makeHidden(['id','phone', 'last_login', 'created_at', 'update_at', 'mfa_enabled', 'updated_at', 'mfa_type', 'mfa_secret']);

        return response()->json([
            'token' => $token,
            'user' => $user,
        ]);
    }

    public function verifyEmailLoginCode(Request $request)
    {
        $user = User::where('email', $request->email)->firstOrFail();
        $code = (string) $request->code;

        $cacheKeyCode = "mfa_login_code_{$user->id}";
        $cacheKeyAttempts = "mfa_login_attempts_{$user->id}";
        $cacheKeyBlocked = "mfa_login_blocked_{$user->id}";

        // Verificar si está bloqueado
        if (Cache::has($cacheKeyBlocked)) {
            $secondsLeft = Cache::get($cacheKeyBlocked) - now()->timestamp;
            return response()->json([
                'message' => 'Demasiados intentos fallidos. Intenta nuevamente en 5 minutos.',
                'retry_after_seconds' => max($secondsLeft, 0)
            ], 429);
        }

        $storedCode = (string) Cache::get($cacheKeyCode);

        if ($code === $storedCode) {
            Cache::forget($cacheKeyCode);
            Cache::forget($cacheKeyAttempts);
            Cache::forget($cacheKeyBlocked);
            return $this->completeLogin($user);
        }

        // Asegura que el valor sea numérico desde el inicio
        $attempts = Cache::get($cacheKeyAttempts);
        if (!is_numeric($attempts)) {
            $attempts = 1;
        } else {
            $attempts++;
        }
        Cache::put($cacheKeyAttempts, $attempts, now()->addMinutes(10));

        if ($attempts >= 3) {
            Cache::forget($cacheKeyCode);
            Cache::put($cacheKeyBlocked, now()->addMinutes(5)->timestamp, now()->addMinutes(5));
            return response()->json([
                'message' => 'Demasiados intentos. Código bloqueado por 5 minutos.'
            ], 423);
        }

        return response()->json([
            'message' => 'Código inválido.',
            'attempts' => $attempts
        ], 422);
    }

    public function verifyAppLoginCode(Request $request)
    {
        $user = User::where('email', $request->email)->firstOrFail();
        $code = (string) $request->code;

        $cacheKeyAttempts = "mfa_app_attempts_{$user->id}";
        $cacheKeyBlocked = "mfa_app_blocked_{$user->id}";

        // Bloqueo temporal
        if (Cache::has($cacheKeyBlocked)) {
            $secondsLeft = Cache::get($cacheKeyBlocked) - now()->timestamp;
            return response()->json([
                'message' => 'Demasiados intentos fallidos. Intenta nuevamente en 5 minutos.',
                'retry_after_seconds' => max($secondsLeft, 0)
            ], 429);
        }

        // Verifica código TOTP
        if (!$user->mfa_secret) {
            return response()->json(['message' => 'Secreto no configurado.'], 400);
        }

        $google2fa = new \PragmaRX\Google2FAQRCode\Google2FA();
        $valid = $google2fa->verifyKey($user->mfa_secret, $code);

        if ($valid) {
            Cache::forget($cacheKeyAttempts);
            Cache::forget($cacheKeyBlocked);
            return $this->completeLogin($user);
        }

        // Control de intentos
        $attempts = Cache::get($cacheKeyAttempts);
        if (!is_numeric($attempts)) {
            $attempts = 1;
        } else {
            $attempts++;
        }

        Cache::put($cacheKeyAttempts, $attempts, now()->addMinutes(10));

        if ($attempts >= 3) {
            Cache::put($cacheKeyBlocked, now()->addMinutes(5)->timestamp, now()->addMinutes(5));
            return response()->json([
                'message' => 'Demasiados intentos. Código bloqueado por 5 minutos.'
            ], 423);
        }

        return response()->json([
            'message' => 'Código inválido.',
            'attempts' => $attempts
        ], 422);
    }




    public function logout(Request $request)
    {
        $token = $request->user()->currentAccessToken();

        if ($token) {
            // Marcar la sesión como revocada
            $session = $request->user()->sessions()
                ->where('token_id', $token->id)
                ->latest('created_at')
                ->first();

            if ($session) {
                $session->update([
                    'revoked_at' => now()
                ]);
            }

            // Eliminar el token
            /** @var \Laravel\Sanctum\PersonalAccessToken $token */
            $token->delete();
        }

        return response()->json(['message' => 'Sesión cerrada']);
    }


    public function me(Request $request)
    {
        return response()->json($request->user());
    }
}
