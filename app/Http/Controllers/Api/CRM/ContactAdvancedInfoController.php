<?php

namespace App\Http\Controllers\Api\CRM;

use App\Http\Controllers\Controller;
use App\Models\Crm\Contact;
use App\Models\Crm\ContactCustomField;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Crm\ContactCustomFieldValue;

class ContactAdvancedInfoController extends Controller
{
    public function show(Contact $contact)
    {
        // Campos personalizados activos
        $customFields = ContactCustomField::where('active', true)
            ->get()
            ->map(function ($field) use ($contact) {
                $value = $contact->customFieldValues
                    ->firstWhere('custom_field_id', $field->id)
                    ->value ?? null;

                return [
                    'id' => $field->id,
                    'label' => $field->label,
                    'type' => $field->type,
                    'options' => $field->options,
                    'value' => $value
                ];
            });

        // Módulos relacionados (solo los más usados en tu caso)
        return response()->json([
            'custom_fields' => $customFields,
            'modules' => [
                'real_state_projects' => [
                    'label' => 'Proyecto de interés',
                    'type' => 'relation',
                    'multiple' => true,
                    'options' => \App\Models\RealState\Project::where('active', true)
                        ->get(['id as value', 'name as label']),
                    'value' => $contact->projects()->pluck('real_state_project_id')
                ],
                'campaigns' => [
                    'label' => 'Campañas',
                    'type' => 'relation',
                    'multiple' => true,
                    'options' => \App\Models\Crm\Campaign::where('active', true)
                        ->get(['id as value', 'name as label']),
                    'value' => $contact->campaigns()->pluck('crm_campaign_id')
                ],
                'origins' => [
                    'label' => 'Orígenes',
                    'type' => 'relation',
                    'multiple' => true,
                    'options' => \App\Models\Crm\Origin::where('active', true)
                        ->get(['id as value', 'name as label']),
                    'value' => $contact->origins()->pluck('crm_origin_id')
                ]
            ]
        ]);
    }

    public function update(Request $request, Contact $contact)
    {
        DB::beginTransaction();
        try {
            // ✅ Actualizar valores de campos personalizados
            foreach ($request->input('custom_fields', []) as $field) {
                ContactCustomFieldValue::updateOrCreate(
                    [
                        'contact_id' => $contact->id,
                        'custom_field_id' => $field['id']
                    ],
                    [
                        'value' => $field['value']
                    ]
                );
            }

            // ✅ Sincronizar módulos relacionados
            if ($request->has('modules.real_state_projects')) {
                $contact->projects()->sync($request->modules['real_state_projects']);
            }
            if ($request->has('modules.campaigns')) {
                $contact->campaigns()->sync($request->modules['campaigns']);
            }
            if ($request->has('modules.origins')) {
                $contact->origins()->sync($request->modules['origins']);
            }

            DB::commit();
            return response()->json(['message' => 'Información avanzada actualizada correctamente']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al actualizar', 'error' => $e->getMessage()], 500);
        }
    }
}
