<?php

namespace App\Http\Controllers\Api\CRM;

use App\Http\Controllers\Controller;
use App\Models\Crm\DealCustomField;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DealCustomFieldController extends Controller
{
    /**
     * Muestra una lista de todos los campos personalizados de Deals.
     */
    public function index()
    {
        return DealCustomField::orderBy('label')->get();
    }

    /**
     * Crea un nuevo campo personalizado para Deals.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'label' => 'required|string|max:255',
            'name' => 'required|string|max:255|unique:crm_deal_custom_fields,name',
            'type' => ['required', Rule::in(['text', 'number', 'select', 'date', 'textarea', 'checkbox'])],
            'options' => 'nullable|array|required_if:type,select',
            'required' => 'boolean',
        ]);

        $field = DealCustomField::create($validated);
        return response()->json($field, 201);
    }

    /**
     * Muestra un campo personalizado de Deal específico.
     */
    public function show(DealCustomField $dealCustomField)
    {
        return $dealCustomField;
    }

    /**
     * Actualiza un campo personalizado de Deal.
     */
    public function update(Request $request, DealCustomField $dealCustomField)
    {
        $validated = $request->validate([
            'label' => 'required|string|max:255',
            'type' => ['required', Rule::in(['text', 'number', 'select', 'date', 'textarea', 'checkbox'])],
            'options' => 'nullable|array|required_if:type,select',
            'required' => 'boolean',
        ]);

        $dealCustomField->update($validated);
        return response()->json($dealCustomField);
    }

    /**
     * Elimina un campo personalizado de Deal.
     */
    public function destroy(DealCustomField $dealCustomField)
    {
        $dealCustomField->delete();
        return response()->json(['message' => 'Campo personalizado de Deal eliminado.']);
    }
}