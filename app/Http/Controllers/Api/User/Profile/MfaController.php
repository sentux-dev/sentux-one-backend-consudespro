<?php

namespace App\Http\Controllers\Api\User\Profile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FAQRCode\Google2FA;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class MfaController extends Controller
{
    public function sendEmailCode(Request $request)
    {
        $user = $request->user();
        $code = rand(100000, 999999);

        Cache::put("mfa_email_code_{$user->id}", $code, now()->addMinutes(10));

        Mail::raw("Tu código de verificación es: $code", function ($message) use ($user) {
            $message->to($user->email)
                ->subject('Código de verificación MFA');
        });

        return response()->json(['message' => 'Código enviado por correo.']);
    }

    public function verifyEmailCode(Request $request)
    {
        $user = $request->user();
        $code = $request->input('code');

        $cacheKeyCode = "mfa_email_code_{$user->id}";
        $cacheKeyAttempts = "mfa_email_attempts_{$user->id}";
        $cacheKeyBlocked = "mfa_email_blocked_{$user->id}";

        // Verifica si el usuario está bloqueado
        if (Cache::has($cacheKeyBlocked)) {
            $minutesLeft = Cache::get($cacheKeyBlocked) - now()->timestamp;
            return response()->json([
                'message' => 'Demasiados intentos fallidos. Intenta nuevamente en 5 minutos.',
                'retry_after_seconds' => $minutesLeft
            ], 429);
        }

        // Verifica el código
        $validCode = (string)$code === (string)Cache::get($cacheKeyCode);

        if ($validCode) {
            $user->update([
                'mfa_enabled' => true,
                'mfa_type' => 'email',
                'mfa_secret' => null
            ]);

            Cache::forget($cacheKeyCode);
            Cache::forget($cacheKeyAttempts);

            return response()->json(['message' => 'MFA activado por correo.']);
        }

        // Suma intento fallido
        $attempts = Cache::increment($cacheKeyAttempts);
        Cache::put($cacheKeyAttempts, $attempts, now()->addMinutes(10));

        if ($attempts >= 3) {
            Cache::forget($cacheKeyCode); // Borra el código
            Cache::put($cacheKeyBlocked, now()->addMinutes(5)->timestamp, now()->addMinutes(5));

            return response()->json([
                'message' => 'Demasiados intentos. MFA bloqueado por 5 minutos.'
            ], 423);
        }

        return response()->json(['message' => 'Código inválido.'], 422);
    }


    public function setupTOTP(Request $request)
    {
        $user = $request->user();
        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();

        $user->update([
            'mfa_secret' => $secret,
            'mfa_type' => 'app'
        ]);

        $qrCodeSvg = $google2fa->getQRCodeInline(
            config('app.name'),
            $user->email,
            $secret
        );

        return response()->json(['qrCodeSvg' => $qrCodeSvg]);
    }

    public function verifyTOTP(Request $request)
    {
        $user = $request->user();
        $code = $request->input('code');

        $google2fa = new Google2FA();

        if (!$user->mfa_secret) {
            return response()->json(['message' => 'No hay secreto configurado.'], 400);
        }

        $valid = $google2fa->verifyKey($user->mfa_secret, $code);

        if ($valid) {
            $user->update(['mfa_enabled' => true]);
            return response()->json(['message' => 'MFA activado con app.']);
        }

        return response()->json(['message' => 'Código inválido.'], 422);
    }

    public function deactivateMFA(Request $request)
    {
        $user = $request->user();

        $user->update([
            'mfa_enabled' => false,
            'mfa_type' => null,
            'mfa_secret' => null
        ]);

        return response()->json(['message' => 'MFA desactivado correctamente.']);
    }
}
