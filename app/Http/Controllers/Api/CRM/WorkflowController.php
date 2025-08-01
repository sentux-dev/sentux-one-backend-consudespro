<?php

namespace App\Http\Controllers\Api\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\Workflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB; // 🔹 Importar DB
use Illuminate\Validation\Rule;

class WorkflowController extends Controller
{
    /**
     * Muestra una lista de todos los workflows.
     */
    public function index()
    {
        return Workflow::orderBy('priority')->get();
    }

    /**
     * Crea un nuevo workflow con sus condiciones y acciones.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:crm_workflows,name',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'priority' => 'integer',
            'conditions' => 'present|array',
            'conditions.*.field' => 'required|string',
            'conditions.*.operator' => 'required|string',
            'conditions.*.value' => 'nullable|string',
            'actions' => 'present|array',
            'actions.*.action_type' => 'required|string',
            'actions.*.parameters' => 'nullable|array',
        ]);

        $workflow = null;
        DB::transaction(function () use ($validated, &$workflow) {
            // 1. Crear el workflow principal
            $workflow = Workflow::create($validated);
            $workflow->refresh(); // Refrescar el modelo para obtener el ID recién creado

            // 2. Crear las condiciones asociadas
            if (!empty($validated['conditions'])) {
                $workflow->conditions()->createMany($validated['conditions']);
            }

            // 3. Crear las acciones asociadas
            if (!empty($validated['actions'])) {
                $workflow->actions()->createMany($validated['actions']);
            }
        });

        if (!$workflow) {
            return response()->json(['message' => 'Error al crear el workflow.'], 500);
        }

        // Devolvemos el workflow con sus relaciones cargadas
        return response()->json($workflow->load(['conditions', 'actions']), 201);
    }

    /**
     * Muestra un workflow específico con sus condiciones y acciones.
     */
    public function show(Workflow $workflow)
    {
        $workflow->load(['conditions', 'actions']);
        return $workflow;
    }

    /**
     * Actualiza un workflow y sincroniza sus condiciones y acciones.
     */
    public function update(Request $request, Workflow $workflow)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('crm_workflows')->ignore($workflow->id)],
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'priority' => 'integer',
            'conditions' => 'present|array',
            'actions' => 'present|array',
        ]);
        
        DB::transaction(function () use ($workflow, $validated) {
            // 1. Actualizar el workflow principal
            $workflow->update($validated);

            // 2. Sincronizar: Eliminar las antiguas y crear las nuevas
            $workflow->conditions()->delete();
            if (!empty($validated['conditions'])) {
                $workflow->conditions()->createMany($validated['conditions']);
            }
            
            $workflow->actions()->delete();
            if (!empty($validated['actions'])) {
                $workflow->actions()->createMany($validated['actions']);
            }
        });

        return response()->json($workflow->load(['conditions', 'actions']));
    }

    /**
     * Elimina un workflow.
     */
    public function destroy(Workflow $workflow)
    {
        $workflow->delete();
        return response()->json(['message' => 'Workflow eliminado.']);
    }
}