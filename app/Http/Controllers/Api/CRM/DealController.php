<?php

namespace App\Http\Controllers\Api\CRM;

use App\Http\Controllers\Controller;
use App\Models\Crm\Deal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class DealController extends Controller
{
    public function index(Request $request)
    {
        $query = Deal::query();

        if ($request->filled('pipeline_id')) {
            $query->where('pipeline_id', $request->pipeline_id);
        }

        if ($request->filled('owner_id')) {
            $query->where('owner_id', $request->owner_id);
        }

        if ($request->filled('contact_id')) {
            $query->where('contact_id', $request->contact_id);
        }

        if ($request->filled('close_date')) {
            $query->whereDate('close_date', $request->close_date);
        }

        $deals = $query->get();

        return response()->json([
            'deals' => $deals,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'amount' => 'nullable|numeric',
            'pipeline_id' => 'required|exists:crm_pipelines,id',
            'stage_id' => 'required|exists:crm_pipeline_stages,id',
        ]);

        $data['owner_id'] = Auth::id();

        $deal = Deal::create($data);

        Log::info('Deal created', [
            'deal_id' => $deal->id,
            'user_id' => Auth::id(),
            'data' => $data
        ]);

        return response()->json($deal, 201);
    }

    public function update(Request $request, Deal $deal)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'amount' => 'nullable|numeric',
            'pipeline_id' => 'required|exists:crm_pipelines,id',
            'stage_id' => 'required|exists:crm_pipeline_stages,id',
        ]);

        $deal->update($data);

        return response()->json($deal->load(['pipeline', 'stage', 'owner']));
    }
}