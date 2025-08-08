<?php

namespace App\Http\Controllers\Api\CRM;

use App\Http\Controllers\Controller;
use App\Models\Crm\Origin;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class OriginController extends Controller
{
    public function index()
    {
        return Origin::orderBy('order')->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:crm_origins,name',
            'active' => 'boolean',
        ]);

        $maxOrder = Origin::max('order') ?? 0;
        $validated['order'] = $maxOrder + 1;

        $origin = Origin::create($validated);
        return response()->json($origin, 201);
    }

    public function update(Request $request, Origin $origin)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('crm_origins')->ignore($origin->id)],
            'active' => 'boolean',
        ]);

        $origin->update($validated);
        return response()->json($origin);
    }

    public function destroy(Origin $origin)
    {
        if ($origin->contacts()->exists()) {
            return response()->json(['message' => 'No se puede eliminar el origen porque está en uso.'], 409);
        }

        $origin->delete();
        return response()->json(['message' => 'Origen eliminado correctamente.']);
    }

    public function updateOrder(Request $request)
    {
        $validated = $request->validate([
            'origins' => 'required|array',
            'origins.*.id' => 'required|integer|exists:crm_origins,id',
            'origins.*.order' => 'required|integer',
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['origins'] as $originData) {
                Origin::where('id', $originData['id'])->update(['order' => $originData['order']]);
            }
        });

        return response()->json(['message' => 'Orden actualizado con éxito.']);
    }
}