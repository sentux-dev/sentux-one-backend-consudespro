<?php

namespace App\Http\Controllers\Api\RealState;

use App\Http\Controllers\Controller;
use App\Models\RealState\Project;
use App\Models\RealState\Extra;
use Illuminate\Http\Request;

class ExtraController extends Controller
{
    // Listar extras para un proyecto específico
    public function index(Project $project)
    {
        return $project->extras()->orderBy('name')->get();
    }

    // Crear un nuevo extra para un proyecto
    public function store(Request $request, Project $project)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
        ]);

        $extra = $project->extras()->create($validated);

        return response()->json($extra, 201);
    }

    // Mostrar un extra específico
    public function show(Extra $extra)
    {
        return $extra;
    }

    // Actualizar un extra
    public function update(Request $request, Extra $extra)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
        ]);

        $extra->update($validated);

        return response()->json($extra);
    }

    // Eliminar un extra
    public function destroy(Extra $extra)
    {
        // Opcional: Añadir lógica para verificar si el extra está en uso
        if ($extra->lots()->exists()) {
            return response()->json(['message' => 'No se puede eliminar el extra porque está asignado a uno o más lotes.'], 409);
        }

        $extra->delete();
        return response()->json(['message' => 'Extra eliminado.']);
    }
}