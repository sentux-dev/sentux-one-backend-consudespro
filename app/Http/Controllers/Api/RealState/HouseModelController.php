<?php

namespace App\Http\Controllers\Api\RealState;

use App\Http\Controllers\Controller;
use App\Models\RealState\Project;
use App\Models\RealState\HouseModel;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HouseModelController extends Controller
{
    // Listar modelos de casa para un proyecto específico
    public function index(Project $project)
    {
        return $project->houseModels()->orderBy('name')->get();
    }

    // Crear un nuevo modelo de casa para un proyecto
    public function store(Request $request, Project $project)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'base_price' => 'required|numeric|min:0',
            'square_footage' => 'required|numeric|min:0',
        ]);

        $houseModel = $project->houseModels()->create($validated);

        return response()->json($houseModel, 201);
    }

    // Mostrar un modelo de casa específico
    public function show(HouseModel $houseModel)
    {
        return $houseModel;
    }

    // Actualizar un modelo de casa
    public function update(Request $request, HouseModel $houseModel)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => 'nullable|string',
            'base_price' => 'required|numeric|min:0',
            'square_footage' => 'required|numeric|min:0',
        ]);

        $houseModel->update($validated);

        return response()->json($houseModel);
    }

    // Eliminar un modelo de casa
    public function destroy(HouseModel $houseModel)
    {
        // Opcional: Añadir lógica para verificar si el modelo está en uso antes de eliminar
        if ($houseModel->lots()->exists()) {
            return response()->json(['message' => 'No se puede eliminar el modelo porque está asignado a uno o más lotes.'], 409);
        }

        $houseModel->delete();
        return response()->json(['message' => 'Modelo de casa eliminado.']);
    }
}