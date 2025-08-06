<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRules;


class ForgotPasswordController extends Controller
{
    /**
     * Maneja la solicitud para enviar el enlace de restablecimiento.
     */
    public function sendResetLinkEmail(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $status = Password::sendResetLink($request->only('email'));

        return $status === Password::RESET_LINK_SENT
            ? response()->json(['message' => '¡Enlace de restablecimiento enviado!'], 200)
            : response()->json(['message' => 'No se puede enviar el enlace a este correo.'], 422);
    }

    /**
     * Maneja el restablecimiento de la contraseña.
     */
    public function reset(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', PasswordRules::defaults()],
        ]);

        $status = Password::reset($request->all(), function ($user, $password) {
            $user->forceFill([
                'password' => Hash::make($password)
            ])->setRememberToken(Str::random(60));
            
            $user->save();
        });

        return $status === Password::PASSWORD_RESET
            ? response()->json(['message' => '¡Contraseña restablecida con éxito!'], 200)
            : response()->json(['message' => 'Token inválido o el correo no coincide.'], 422);
    }
}