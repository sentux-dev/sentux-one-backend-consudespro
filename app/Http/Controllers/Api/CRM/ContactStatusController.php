<?php

namespace App\Http\Controllers\Api\CRM;

use App\Http\Controllers\Controller;
use App\Models\Crm\ContactStatus;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class ContactStatusController extends Controller
{
    public function index()
    {
        return ContactStatus::orderBy('order')->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:crm_contact_statuses,name',
            'active' => 'boolean',
        ]);

        $maxOrder = ContactStatus::max('order') ?? 0;
        $validated['order'] = $maxOrder + 1;

        $status = ContactStatus::create($validated);
        return response()->json($status, 201);
    }

    public function update(Request $request, ContactStatus $contactStatus)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('crm_contact_statuses')->ignore($contactStatus->id)],
            'active' => 'boolean',
        ]);

        $contactStatus->update($validated);
        return response()->json($contactStatus);
    }

    public function destroy(ContactStatus $contactStatus)
    {
        if ($contactStatus->contacts()->exists()) {
            return response()->json(['message' => 'No se puede eliminar el estado porque está en uso por uno o más contactos.'], 409);
        }

        $contactStatus->delete();
        return response()->json(['message' => 'Estado eliminado correctamente.']);
    }
    
    public function updateOrder(Request $request)
    {
        $validated = $request->validate([
            'statuses' => 'required|array',
            'statuses.*.id' => 'required|integer|exists:crm_contact_statuses,id',
            'statuses.*.order' => 'required|integer',
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['statuses'] as $statusData) {
                ContactStatus::where('id', $statusData['id'])->update(['order' => $statusData['order']]);
            }
        });

        return response()->json(['message' => 'Orden actualizado con éxito.']);
    }
}