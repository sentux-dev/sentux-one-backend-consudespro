<?php

namespace App\Http\Controllers\Api\RealState;

use App\Http\Controllers\Controller;
use App\Models\RealState\Lot;
use Illuminate\Http\Request;

class LotController extends Controller
{
    // Mostrar la información detallada de un lote con todas sus relaciones
    public function show(Lot $lot)
    {
        $lot->load([
            'project:id,name,slug', 
            'houseModel', 
            'seller:id,name', 
            'formalizer:id,name', 
            'owners', // Contactos dueños
            'extras', 
            'adjustments.user:id,name', // Ajustes y quién los hizo
            'documents.user:id,name'    // Documentos y quién los subió
        ]);
        return $lot;
    }

    // Actualizar la información principal de un lote
    public function update(Request $request, Lot $lot)
    {
        $validated = $request->validate([
            'house_model_id' => 'nullable|exists:real_state_house_models,id',
            'seller_id' => 'nullable|exists:users,id',
            'formalizer_id' => 'nullable|exists:users,id',
            'base_price' => 'required|numeric',
            'extra_footage' => 'nullable|numeric',
            'extra_footage_cost' => 'nullable|numeric',
            'down_payment_percentage' => 'nullable|numeric|min:0|max:100',
            'reservation_date' => 'nullable|date',
            'delivery_date' => 'nullable|date',
            'contract_signing_date' => 'nullable|date',
            'contract_due_date' => 'nullable|date',
            'house_delivery_date' => 'nullable|date',
            'status' => 'required|string', // ej: 'Disponible', 'Reservado', 'Vendido'
        ]);
        
        // Lógica adicional para asegurar que el house_model_id pertenezca al proyecto del lote
        if ($request->house_model_id) {
            $houseModel = \App\Models\RealState\HouseModel::find($request->house_model_id);
            if ($houseModel->project_id !== $lot->project_id) {
                return response()->json(['message' => 'El modelo de casa no pertenece a este proyecto.'], 422);
            }
        }

        $lot->update($validated);

        // Sincronizar dueños (contactos) y extras
        if ($request->has('owner_ids')) {
            $lot->owners()->sync($request->input('owner_ids', []));
        }
        if ($request->has('extra_ids')) {
            $lot->extras()->sync($request->input('extra_ids', []));
        }

        return $this->show($lot); // Devolver el lote actualizado con todas las relaciones
    }
}