<?php

namespace App\Http\Controllers\Api\CRM;

use App\Http\Controllers\Controller;
use App\Models\Crm\Pipeline;
use App\Models\Crm\PipelineStage;
use Illuminate\Http\Request;

class PipelineController extends Controller
{
    public function index()
    {
        return Pipeline::with('stages')->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255'
        ]);

        $pipeline = Pipeline::create($validated);
        return response()->json(['pipeline' => $pipeline], 201);
    }

    public function update(Request $request, Pipeline $pipeline)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255'
        ]);

        $pipeline->update($validated);
        return response()->json(['pipeline' => $pipeline]);
    }

    public function destroy(Pipeline $pipeline)
    {
        $pipeline->delete();
        return response()->json(['message' => 'Pipeline eliminado']);
    }

    public function addStage(Request $request, Pipeline $pipeline)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255'
        ]);

        $order = $pipeline->stages()->count();

        $stage = $pipeline->stages()->create([
            'name' => $validated['name'],
            'order' => $order
        ]);

        return response()->json(['stage' => $stage], 201);
    }

    public function updateStage(Request $request, PipelineStage $stage)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255'
        ]);

        $stage->update($validated);
        return response()->json(['stage' => $stage]);
    }

    public function deleteStage(PipelineStage $stage)
    {
        $stage->delete();
        return response()->json(['message' => 'Etapa eliminada']);
    }

    public function reorderStages(Request $request, Pipeline $pipeline)
    {
        $validated = $request->validate([
            'stages' => 'required|array',
            'stages.*.id' => 'required|integer|exists:crm_pipeline_stages,id',
            'stages.*.order' => 'required|integer'
        ]);

        foreach ($validated['stages'] as $stageData) {
            PipelineStage::where('id', $stageData['id'])
                ->where('pipeline_id', $pipeline->id)
                ->update(['order' => $stageData['order']]);
        }

        return response()->json(['message' => 'Orden actualizado']);
    }
}