<?php

namespace App\Http\Controllers\Api\CRM;

use App\Http\Controllers\Controller;
use App\Models\Crm\Campaign;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class CampaignController extends Controller
{
    public function index()
    {
        return Campaign::orderBy('order')->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:crm_campaigns,name',
            'active' => 'boolean',
        ]);

        $maxOrder = Campaign::max('order') ?? 0;
        $validated['order'] = $maxOrder + 1;

        $campaign = Campaign::create($validated);
        return response()->json($campaign, 201);
    }

    public function update(Request $request, Campaign $campaign)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('crm_campaigns')->ignore($campaign->id)],
            'active' => 'boolean',
        ]);

        $campaign->update($validated);
        return response()->json($campaign);
    }

    public function destroy(Campaign $campaign)
    {
        if ($campaign->contacts()->exists()) {
            return response()->json(['message' => 'No se puede eliminar la campaña porque está en uso.'], 409);
        }

        $campaign->delete();
        return response()->json(['message' => 'Campaña eliminada correctamente.']);
    }

    public function updateOrder(Request $request)
    {
        $validated = $request->validate([
            'campaigns' => 'required|array',
            'campaigns.*.id' => 'required|integer|exists:crm_campaigns,id',
            'campaigns.*.order' => 'required|integer',
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['campaigns'] as $campaignData) {
                Campaign::where('id', $campaignData['id'])->update(['order' => $campaignData['order']]);
            }
        });

        return response()->json(['message' => 'Orden actualizado con éxito.']);
    }
}