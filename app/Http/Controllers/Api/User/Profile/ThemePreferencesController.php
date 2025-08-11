<?php

namespace App\Http\Controllers\Api\User\Profile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ThemePreferencesController extends Controller
{
    /**
     * Muestra las preferencias de tema del usuario autenticado.
     */
    public function show(Request $request)
    {
        return response()->json($request->user()->theme_preferences ?? []);
    }

    /**
     * Actualiza las preferencias de tema del usuario autenticado.
     */
    public function update(Request $request)
    {
        // ✅ Añadimos las propiedades que faltaban a la validación
        $validated = $request->validate([
            'darkTheme' => 'sometimes|boolean',
            'menuMode' => 'sometimes|string',
            'theme' => 'sometimes|string',
            'scale' => 'sometimes|integer',
            'ripple' => 'sometimes|boolean',
            'preset' => 'sometimes|string',
            'primary' => 'sometimes|string',
            'surface' => 'nullable|string',
        ]);

        $user = $request->user();
        
        $user->theme_preferences = array_merge($user->theme_preferences ?? [], $validated);
        $user->save();
        
        return response()->json($user->theme_preferences);
    }
}