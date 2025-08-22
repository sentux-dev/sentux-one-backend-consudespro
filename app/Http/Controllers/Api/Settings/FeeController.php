<?php
namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Models\Settings\Fee;
use Illuminate\Http\Request;

class FeeController extends Controller
{
    public function index() {
        return Fee::where('is_active', true)->orderBy('name')->get();
    }

    public function store(Request $request) {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:percentage,fixed',
            'value' => 'required|numeric|min:0',
            'is_taxable' => 'required|boolean',
            'is_active' => 'required|boolean',
        ]);
        return Fee::create($validated);
    }

    public function update(Request $request, Fee $fee) {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'type' => 'sometimes|required|in:percentage,fixed',
            'value' => 'sometimes|required|numeric|min:0',
            'is_taxable' => 'sometimes|required|boolean',
            'is_active' => 'sometimes|required|boolean',
        ]);
        $fee->update($validated);
        return $fee;
    }

    public function destroy(Fee $fee) {
        $fee->delete();
        return response()->noContent();
    }
}