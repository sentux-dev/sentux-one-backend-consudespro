<?php

namespace App\Http\Controllers\Api\RealState;

use App\Http\Controllers\Controller;
use App\Models\RealState\Lot;
use App\Models\RealState\LotAdjustment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class LotAdjustmentController extends Controller
{
    /**
     * Añade un nuevo ajuste (descuento o regalía) a un lote.
     */
    public function store(Request $request, Lot $lot)
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['Descuento', 'Regalia'])],
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
        ]);

        $adjustment = $lot->adjustments()->create([
            'type' => $validated['type'],
            'description' => $validated['description'],
            'amount' => $validated['amount'],
            'user_id' => Auth::id(), // Asigna el usuario autenticado
        ]);

        return response()->json($adjustment, 201);
    }

    /**
     * Elimina un ajuste de un lote.
     */
    public function destroy(LotAdjustment $adjustment)
    {
        // Opcional: Se podría añadir una política de seguridad para verificar
        // que el usuario tiene permiso para eliminar este ajuste.
        $adjustment->delete();

        return response()->json(['message' => 'Ajuste eliminado correctamente.']);
    }
}