<?php

namespace App\Http\Controllers\Api\CRM;

use App\Http\Controllers\Controller;
use App\Models\Crm\LeadSource;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeadSourceController extends Controller
{
    public function index()
    {
        return LeadSource::latest()->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'source_key' => 'required|string|max:255|unique:crm_lead_sources,source_key',
            'allowed_domains' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        $leadSource = LeadSource::create($validated);
        return response()->json($leadSource, 201);
    }

    public function update(Request $request, LeadSource $leadSource)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'source_key' => ['required', 'string', 'max:255', Rule::unique('crm_lead_sources')->ignore($leadSource->id)],
            'allowed_domains' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        $leadSource->update($validated);
        return response()->json($leadSource);
    }

    public function destroy(LeadSource $leadSource)
    {
        $leadSource->delete();
        return response()->json(['message' => 'Fuente de lead eliminada.']);
    }
}