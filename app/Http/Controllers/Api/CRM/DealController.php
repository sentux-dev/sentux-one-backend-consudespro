<?php

namespace App\Http\Controllers\Api\CRM;

use App\Http\Controllers\Controller;
use App\Models\Crm\Contact;
use App\Models\Crm\Deal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DealController extends Controller
{
    public function index(Request $request)
    {
        // Añadido with('dealAssociations.associable') para cargar el contacto asociado
        $query = Deal::query()->with('dealAssociations.associable');

        if ($request->filled('pipeline_id')) {
            $query->where('pipeline_id', $request->pipeline_id);
        }

        if ($request->filled('owner_id')) {
            $query->where('owner_id', $request->owner_id);
        }

        if ($request->filled('contact_id')) {
            $query->whereHas('dealAssociations', function ($q) use ($request) {
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
        // Cargar todas las relaciones necesarias, incluyendo las asociaciones con contacto
        $deal->load(['pipeline', 'stage', 'owner', 'dealAssociations.associable']);
        return response()->json($deal);
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'amount' => 'nullable|numeric',
            'close_date' => 'nullable|date',
            'pipeline_id' => 'required|exists:crm_pipelines,id',
            'stage_id' => 'required|exists:crm_pipeline_stages,id',
            // 🔹 Aceptamos un array de contact_ids
            'contact_ids' => 'nullable|array',
            'contact_ids.*' => 'integer|exists:crm_contacts,id',
        ]);

        $deal = null;
        DB::beginTransaction();
        try {
            $dealData = $request->except('contact_ids');
            $dealData['owner_id'] = Auth::id();

            $deal = Deal::create($dealData);

            if (!empty($validatedData['contact_ids'])) {
                foreach ($validatedData['contact_ids'] as $contactId) {
                    $contact = Contact::find($contactId);
                    if ($contact) {
                        $deal->associate($contact, 'deal-contact');
                    }
                }
            }

            DB::commit();

            Log::info('Deal created', ['deal_id' => $deal->id]);
            $deal->load('dealAssociations.associable');
            return response()->json($deal, 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating deal', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Error al crear el negocio'], 500);
        }
    }

    public function update(Request $request, Deal $deal)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'amount' => 'nullable|numeric',
            'close_date' => 'nullable|date',
            'pipeline_id' => 'required|exists:crm_pipelines,id',
            'stage_id' => 'required|exists:crm_pipeline_stages,id',
            // 🔹 Aceptamos un array de contact_ids
            'contact_ids' => 'nullable|array',
            'contact_ids.*' => 'integer|exists:crm_contacts,id',
        ]);

        DB::beginTransaction();
        try {
            $deal->update($request->except('contact_ids'));

            // 🔹 Lógica de Sincronización
            if (isset($validatedData['contact_ids'])) {
                // 1. Eliminar asociaciones de contacto que ya no están en la lista
                $deal->dealAssociations()
                     ->where('associable_type', Contact::class)
                     ->whereNotIn('associable_id', $validatedData['contact_ids'])
                     ->delete();

                // 2. Añadir las nuevas asociaciones, evitando duplicados
                foreach ($validatedData['contact_ids'] as $contactId) {
                    $deal->dealAssociations()->firstOrCreate(
                        [
                            'associable_id' => $contactId,
                            'associable_type' => Contact::class
                        ],
                        [
                            'relation_type' => 'deal-contact'
                        ]
                    );
                }
            }

            DB::commit();

            $deal->load(['pipeline', 'stage', 'owner', 'dealAssociations.associable']);
            return response()->json($deal);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating deal', ['deal_id' => $deal->id, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Error al actualizar el negocio'], 500);
        }
    }

}