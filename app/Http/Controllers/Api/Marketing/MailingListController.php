<?php

namespace App\Http\Controllers\Api\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Marketing\MailingList;
use App\Models\Crm\Contact;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MailingListController extends Controller
{
    /**
     * Muestra una lista de todas las listas de correo.
     */
    public function index()
    {
        return MailingList::withCount('contacts')->latest()->get();
    }

    /**
     * Crea una nueva lista de correo.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:marketing_mailing_lists,name',
            'description' => 'nullable|string',
            'contact_ids' => 'nullable|array',
            'contact_ids.*' => 'integer|exists:crm_contacts,id',
        ]);

        $mailingList = MailingList::create($validated);

        if (!empty($validated['contact_ids'])) {
            $mailingList->contacts()->sync($validated['contact_ids']);
        }

        return response()->json($mailingList->loadCount('contacts'), 201);
    }

    /**
     * Muestra una lista de correo específica con sus contactos.
     */
    public function show(MailingList $mailingList)
    {
        // Cargar los contactos con paginación para no sobrecargar la respuesta
        $contacts = $mailingList->contacts()->paginate(50);
        
        return response()->json([
            'list' => $mailingList,
            'contacts' => $contacts
        ]);
    }

    /**
     * Actualiza una lista de correo.
     */
    public function update(Request $request, MailingList $mailingList)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('marketing_mailing_lists')->ignore($mailingList->id)],
            'description' => 'nullable|string',
            'contact_ids' => 'nullable|array',
            'contact_ids.*' => 'integer|exists:crm_contacts,id',
        ]);

        $mailingList->update($validated);

        if ($request->has('contact_ids')) {
            $mailingList->contacts()->sync($validated['contact_ids']);
        }

        return response()->json($mailingList->loadCount('contacts'));
    }

    /**
     * Elimina una lista de correo.
     */
    public function destroy(MailingList $mailingList)
    {
        $mailingList->delete();
        return response()->json(['message' => 'Lista de correo eliminada.']);
    }
}