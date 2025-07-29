<?php

namespace App\Http\Controllers\Api\CRM;

use App\Http\Controllers\Controller;
use App\Models\Crm\DealCustomFieldValue;
use Illuminate\Http\Request;

class DealCustomFieldValueController extends Controller
{
    public function index($dealId)
    {
        return DealCustomFieldValue::with('field')
            ->where('deal_id', $dealId)
            ->get();
    }

    public function storeOrUpdate(Request $request, $dealId)
    {
        $values = collect($request->input('values', []));

        if ($values->isEmpty()) {
            DealCustomFieldValue::where('deal_id', $dealId)->delete();
            return response()->json(['message' => 'Todos los valores personalizados fueron eliminados.']);
        }

        $request->validate([
            'values' => 'array',
            'values.*.custom_field_id' => 'required|exists:crm_deal_custom_fields,id',
            'values.*.value' => ['nullable', 'max:10000'], // permite string, null, num, bool
        ]);

        $incomingIds = $values->pluck('custom_field_id')->unique();

        DealCustomFieldValue::where('deal_id', $dealId)
            ->whereNotIn('custom_field_id', $incomingIds)
            ->delete();

        foreach ($values as $fieldValue) {
            DealCustomFieldValue::updateOrCreate(
                [
                    'deal_id' => $dealId,
                    'custom_field_id' => $fieldValue['custom_field_id'],
                ],
                [
                    'value' => isset($fieldValue['value']) ? (string) $fieldValue['value'] : null,
                ]
            );
        }

        return response()->json(['message' => 'Custom field values saved successfully.']);
    }
}