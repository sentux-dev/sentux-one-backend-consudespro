<?php

namespace App\Http\Controllers\Api\CRM;

use App\Http\Controllers\Controller;
use App\Models\Crm\ContactCustomField;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ContactCustomFieldController extends Controller
{
    /**
     * Listar todos los campos personalizados activos.
     */
    public function index(Request $request)
    {
        $query = ContactCustomField::query();

        if ($request->filled('active')) {
            $query->where('active', $request->boolean('active'));
        }

        return response()->json([
            'data' => $query->orderBy('created_at', 'desc')->get()
        ]);
    }

    /**
     * Crear un nuevo campo personalizado.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:crm_contact_custom_fields,name',
            'label' => 'required|string|max:255',
            'type' => 'required|in:text,number,select,date',
            'options' => 'nullable|array', // solo si type=select
            'active' => 'boolean'
        ]);

        $field = ContactCustomField::create([
            'name' => $validated['name'],
            'label' => $validated['label'],
            'type' => $validated['type'],
            'options' => $validated['options'] ?? null,
            'active' => $validated['active'] ?? true
        ]);

        return response()->json([
            'message' => 'Campo personalizado creado correctamente',
            'data' => $field
        ], 201);
    }

    /**
     * Actualizar un campo personalizado.
     */
    public function update(Request $request, ContactCustomField $field)
    {
        $validated = $request->validate([
            'label' => 'sometimes|required|string|max:255',
            'type' => 'sometimes|required|in:text,number,select,date',
            'options' => 'nullable|array',
            'active' => 'boolean'
        ]);

        $field->update($validated);

        return response()->json([
            'message' => 'Campo personalizado actualizado correctamente',
            'data' => $field
        ]);
    }

    /**
     * Desactivar (soft delete lógico).
     */
    public function deactivate(ContactCustomField $field)
    {
        $field->update(['active' => false]);

        return response()->json([
            'message' => 'Campo personalizado desactivado correctamente'
        ]);
    }
}