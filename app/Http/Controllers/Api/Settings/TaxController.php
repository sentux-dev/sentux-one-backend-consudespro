<?php
namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Models\Settings\Tax;
use Illuminate\Http\Request;

class TaxController extends Controller
{
    public function index() {
        return Tax::orderBy('name')->get();
    }

    public function store(Request $request) {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'rate' => 'required|numeric|min:0',
            'type' => 'required|in:iva,retencion,percepcion,otro',
            'calculation_type' => 'required|in:percentage,fixed',
        ]);
        return Tax::create($validated);
    }

    public function update(Request $request, Tax $tax) {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'rate' => 'sometimes|required|numeric|min:0',
            'type' => 'sometimes|required|in:iva,retencion,percepcion,otro',
            'calculation_type' => 'sometimes|required|in:percentage,fixed',
        ]);
        $tax->update($validated);
        return $tax;
    }

    public function destroy(Tax $tax) {
        $tax->delete();
        return response()->noContent();
    }
}