<?php

namespace App\Http\Controllers\Api\User\Profile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class PreferencesController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'language'      => $user->language,
            'dateFormat'    => $user->date_format,
            'numberFormat'  => $user->number_format,
            'timezone'      => $user->timezone,
            'timeFormat'    => $user->time_format
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'language'      => ['required', Rule::in(['es', 'en'])],
            'dateFormat'    => ['required', 'string', 'max:20'],
            'numberFormat'  => ['required', Rule::in(['european', 'american'])],
            'timezone'      => ['required', 'string', 'max:50'], // opcionalmente validar contra lista de zonas válidas
            'timeFormat'   => ['required', 'string', 'max:10'], // Ej: 'HH:mm' o 'hh:mm A'
        ]);

        $user->update([
            'language'       => $validated['language'],
            'date_format'    => $validated['dateFormat'],
            'number_format'  => $validated['numberFormat'],
            'timezone'       => $validated['timezone'],
            'time_format'    => $validated['timeFormat'],
        ]);

        return response()->json(['message' => 'Preferencias actualizadas correctamente']);
    }
}
