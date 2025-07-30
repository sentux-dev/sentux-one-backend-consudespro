<?php

namespace App\Http\Controllers\Api\RealState;

use App\Http\Controllers\Controller;
use App\Models\RealState\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    public function index()
    {
        return Project::latest()->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:real_state_projects,name',
            'square_footage' => 'nullable|numeric',
            'infrastructure_cost' => 'nullable|numeric',
            'development_cost' => 'nullable|numeric',
            'lot_quantity' => 'required|integer|min:1',
            'status' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            $project = Project::create($validated);

            // Crear los lotes automáticamente
            for ($i = 1; $i <= $project->lot_quantity; $i++) {
                $project->lots()->create([
                    'lot_number' => 'Lote ' . $i,
                    'size' => 0, // Tamaño por defecto
                ]);
            }

            DB::commit();

            return response()->json($project, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al crear el proyecto y sus lotes.'], 500);
        }
    }

    public function show(Project $project)
    {
        // Cargar lotes para el dashboard
        $project->load('lots');
        return $project;
    }

    public function update(Request $request, Project $project)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('real_state_projects')->ignore($project->id)],
            'square_footage' => 'nullable|numeric',
            'infrastructure_cost' => 'nullable|numeric',
            'development_cost' => 'nullable|numeric',
            'status' => 'nullable|string',
        ]);

        $project->update($validated);

        return response()->json($project);
    }

    public function destroy(Project $project)
    {
        $project->delete();
        return response()->json(['message' => 'Proyecto eliminado.']);
    }
}