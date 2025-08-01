<?php

namespace App\Http\Controllers\Api\CRM;

use App\Http\Controllers\Controller;
use App\Models\Crm\ContactCustomFieldValue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ContactCustomFieldValueController extends Controller
{
    /**
     * Listar valores personalizados de un contacto.
     */
    public function index($contactId)
    {
        $values = ContactCustomFieldValue::with('customField')
            ->where('contact_id', $contactId)
            ->get();

        return response()->json([
            'data' => $values->map(function ($v) {
                return [
                    'id' => $v->id,
                    'field_id' => $v->customField->id,
                    'name' => $v->customField->name,
                    'label' => $v->customField->label,
                    'type' => $v->customField->type,
                    'options' => $v->customField->options,
                    'value' => $v->value
                ];
            })
        ]);
    }

    /**
     * Crear o actualizar valores personalizados de un contacto.
     */
    public function storeOrUpdate(Request $request, $contactId)
    {
        $validated = $request->validate([
            'fields' => 'required|array',
            'fields.*.field_id' => 'required|exists:crm_contact_custom_fields,id',
            'fields.*.value' => 'nullable'
        ]);

        DB::beginTransaction();
        try {
            foreach ($validated['fields'] as $field) {
                ContactCustomFieldValue::updateOrCreate(
                    [
                        'contact_id' => $contactId,
                        'custom_field_id' => $field['field_id']
                    ],
                    ['value' => $field['value']]
                );
            }

            DB::commit();
            return response()->json([
                'message' => 'Campos personalizados actualizados correctamente'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al actualizar campos personalizados',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}