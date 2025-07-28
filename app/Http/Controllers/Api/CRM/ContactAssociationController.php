<?php

namespace App\Http\Controllers\Api\CRM;

use App\Http\Controllers\Controller;
use App\Models\CRM\ContactAssociation;
use Illuminate\Http\Request;

class ContactAssociationController extends Controller
{
    // ✅ Listar asociaciones de un contacto
    public function index($contactId)
    {
        $associations = ContactAssociation::with('associatedContact')
            ->where('contact_id', $contactId)
            ->get();

        return response()->json([
            'contacts' => $associations->where('association_type', 'contacts')->map(fn($a) => [
                'id' => $a->associatedContact->id,
                'first_name' => $a->associatedContact->first_name,
                'last_name' => $a->associatedContact->last_name,
                'email' => $a->associatedContact->email,
                'relation_type' => $a->relation_type
            ])->values(),
            'companies' => $associations->where('association_type', 'companies')->values(),
            'deals' => $associations->where('association_type', 'deals')->values(),
            'tickets' => $associations->where('association_type', 'tickets')->values(),
            'orders' => $associations->where('association_type', 'orders')->values(),
        ]);
    }

    public function store(Request $request, $contactId)
    {
        $validated = $request->validate([
            'associated_contact_id' => 'required|exists:crm_contacts,id',
            'association_type' => 'required|string|in:contacts,companies,deals,tickets,orders',
            'relation_type' => 'nullable|string|max:255'
        ]);

        $association = ContactAssociation::create([
            'contact_id' => $contactId,
            'associated_contact_id' => $validated['associated_contact_id'],
            'association_type' => $validated['association_type'],
            'relation_type' => $validated['relation_type'] ?? null
        ]);

        return response()->json([
            'message' => 'Asociación creada correctamente',
            'association' => $association->load('associatedContact')
        ]);
    }
    // ✅ Eliminar una asociación
    public function destroy($contactId, $associatedContactId)
    {
        $association = ContactAssociation::where('contact_id', $contactId)
            ->where('associated_contact_id', $associatedContactId)
            ->firstOrFail();

        $association->delete();

        return response()->json([
            'message' => 'Asociación eliminada correctamente.',
        ]);
    }
}