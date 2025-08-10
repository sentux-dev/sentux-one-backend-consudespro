<?php

namespace App\Http\Controllers\Api\CRM;

use App\Http\Controllers\Controller;
use App\Models\Crm\Sequence;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class SequenceController extends Controller
{
    /**
     * Muestra una lista de todas las secuencias.
     */
    public function index()
    {
        // Devuelve las secuencias con la cantidad de pasos que tiene cada una
        return Sequence::withCount('steps')->orderBy('name')->get();
    }

    /**
     * Guarda una nueva secuencia y sus pasos en la base de datos.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:crm_sequences,name',
            'description' => 'nullable|string',
            'active' => 'boolean',
            'steps' => 'sometimes|array',
            'steps.*.order' => 'required|integer',
            'steps.*.delay_amount' => 'required|integer|min:1',
            'steps.*.delay_unit' => ['required', Rule::in(['minutes', 'hours', 'days'])],
            'steps.*.action_type' => ['required', Rule::in(['send_email_template', 'create_manual_task'])],
            'steps.*.parameters' => 'required|array',
        ]);

        $sequence = DB::transaction(function () use ($validated) {
            $sequence = Sequence::create($validated);

            if (!empty($validated['steps'])) {
                $sequence->steps()->createMany($validated['steps']);
            }

            return $sequence;
        });

        return response()->json($sequence->load('steps'), 201);
    }

    /**
     * Muestra una secuencia específica con todos sus pasos.
     */
    public function show(Sequence $sequence)
    {
        // Carga la relación 'steps' para devolver la secuencia completa
        return $sequence->load('steps');
    }

    /**
     * Actualiza una secuencia existente y sincroniza sus pasos.
     */
    public function update(Request $request, Sequence $sequence)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('crm_sequences')->ignore($sequence->id)],
            'description' => 'nullable|string',
            'active' => 'boolean',
            'steps' => 'sometimes|array',
            'steps.*.order' => 'required|integer',
            'steps.*.delay_amount' => 'required|integer|min:1',
            'steps.*.delay_unit' => ['required', Rule::in(['minutes', 'hours', 'days'])],
            'steps.*.action_type' => ['required', Rule::in(['send_email_template', 'create_manual_task'])],
            'steps.*.parameters' => 'required|array',
        ]);

        DB::transaction(function () use ($sequence, $validated) {
            // 1. Actualiza los datos de la secuencia principal
            $sequence->update($validated);

            // 2. Sincroniza los pasos: borra los antiguos y crea los nuevos
            $sequence->steps()->delete();
            if (!empty($validated['steps'])) {
                $sequence->steps()->createMany($validated['steps']);
            }
        });

        return response()->json($sequence->load('steps'));
    }

    /**
     * Elimina una secuencia.
     */
    public function destroy(Sequence $sequence)
    {
        // Lógica de seguridad: no permitir eliminar si hay contactos inscritos en ella
        if ($sequence->enrollments()->exists()) { // Asumiendo que defines la relación 'enrollments' en el modelo Sequence
            return response()->json([
                'message' => 'No se puede eliminar la secuencia porque tiene contactos inscritos.'
            ], 409); // 409 Conflict
        }

        $sequence->delete();
        
        return response()->json(['message' => 'Secuencia eliminada correctamente.']);
    }
}