<?php

namespace App\Http\Controllers\Api\CRM;

use App\Http\Controllers\Controller;
use App\Models\Crm\Contact;
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
            // Filtrar deals por asociación con un contacto específico
            $query->whereHas('associations', function ($q) use ($request) {
                $q->where('associable_id', $request->contact_id)
                  ->where('associable_type', Contact::class);
            });
        }

        if ($request->filled('close_date')) {
            $query->whereDate('close_date', $request->close_date);
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        $deals = $query->get();

        return response()->json([
            'deals' => $deals,
        ]);
    }

    public function show(Deal $deal)
    {
        return response()->json($deal->load(['pipeline', 'stage', 'owner']));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'amount' => 'nullable|numeric',
            'close_date' => 'nullable|date', // Agregar validación para close_date
            'pipeline_id' => 'required|exists:crm_pipelines,id',
            'stage_id' => 'required|exists:crm_pipeline_stages,id',
            'contact_id' => 'nullable|exists:crm_contacts,id', // Validar contact_id opcional
        ]);

        $data['owner_id'] = Auth::id();

        $deal = Deal::create($data);

        // Si se proporcionó un contact_id, crear la asociación
        if (isset($data['contact_id'])) {
            $contact = Contact::find($data['contact_id']);
            if ($contact) {
                // Usar el método 'associate' definido en el modelo Deal
                $deal->associate($contact, 'deal-contact'); // Puedes definir el tipo de relación
                Log::info('Contact associated with new deal', [
                    'deal_id' => $deal->id,
                    'contact_id' => $contact->id,
                    'relation_type' => 'deal-contact'
                ]);
            }
        }

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
            'close_date' => 'nullable|date', // Agregar validación para close_date
            'pipeline_id' => 'required|exists:crm_pipelines,id',
            'stage_id' => 'required|exists:crm_pipeline_stages,id',
        ]);

        $deal->update($data);

        return response()->json($deal->load(['pipeline', 'stage', 'owner']));
    }
}