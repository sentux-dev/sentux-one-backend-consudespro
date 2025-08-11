<?php

namespace App\Http\Controllers\Api\CRM;

use App\Http\Controllers\Controller;
use App\Models\Crm\DisqualificationReason;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DisqualificationReasonController extends Controller
{
    public function index()
    {
        return DisqualificationReason::orderBy('order')->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:crm_disqualification_reasons,name',
            'active' => 'boolean',
        ]);

        $reason = DisqualificationReason::create($validated);
        return response()->json($reason, 201);
    }

    public function update(Request $request, DisqualificationReason $disqualificationReason)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('crm_disqualification_reasons')->ignore($disqualificationReason->id)],
            'active' => 'boolean',
        ]);

        $disqualificationReason->update($validated);
        return response()->json($disqualificationReason);
    }

    public function destroy(DisqualificationReason $disqualificationReason)
    {
        if ($disqualificationReason->contacts()->exists()) {
            return response()->json(['message' => 'No se puede eliminar la razón porque está en uso por uno o más contactos.'], 409);
        }

        $disqualificationReason->delete();
        return response()->json(['message' => 'Razón de descalificación eliminada.']);
    }

    public function updateOrder(Request $request)
    {
        $validated = $request->validate([
            'reasons' => 'required|array',
            'reasons.*.id' => 'required|integer|exists:crm_disqualification_reasons,id',
            'reasons.*.order' => 'required|integer',
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['reasons'] as $reasonData) {
                DisqualificationReason::where('id', $reasonData['id'])->update(['order' => $reasonData['order']]);
            }
        });

        return response()->json(['message' => 'Orden actualizado con éxito.']);
    }
}