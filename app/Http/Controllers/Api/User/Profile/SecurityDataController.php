<?php

namespace App\Http\Controllers\Api\User\Profile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class SecurityDataController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'email' => $user->email,
            'mfaEnabled' => $user->mfa_enabled,
            'mfaType' => $user->mfa_type,
            'lastLogin' => $user->last_login_at,
        ]);
    }

    public function updatePassword(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'currentPassword' => 'required|string',
            'newPassword' => 'required|string|min:6|confirmed',
        ]);

        if (!Hash::check($validated['currentPassword'], $user->password)) {
            return response()->json(['message' => 'Contraseña actual incorrecta'], 403);
        }

        $user->password = bcrypt($validated['newPassword']);
        $user->save();

        return response()->json(['message' => 'Contraseña actualizada correctamente']);

    }
    
}
