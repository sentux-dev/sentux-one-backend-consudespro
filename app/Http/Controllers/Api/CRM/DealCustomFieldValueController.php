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
        $validated = $request->validate([
            'values' => 'required|array',
            'values.*.custom_field_id' => 'required|exists:crm_deal_custom_fields,id',
            'values.*.value' => 'nullable|string|max:10000',
        ]);

        foreach ($validated['values'] as $fieldValue) {
            DealCustomFieldValue::updateOrCreate(
                [
                    'deal_id' => $dealId,
                    'custom_field_id' => $fieldValue['custom_field_id'],
                ],
                [
                    'value' => $fieldValue['value'],
                ]
            );
        }

        return response()->json(['message' => 'Custom field values saved successfully.']);
    }
}