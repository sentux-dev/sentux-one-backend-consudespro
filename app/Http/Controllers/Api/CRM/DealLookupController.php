<?php

namespace App\Http\Controllers\Api\CRM;

use App\Http\Controllers\Controller;
use App\Models\Crm\DealCustomField;

class DealLookupController extends Controller
{
    /**
     * Devuelve una lista de campos personalizados de tipo 'select' para los deals.
     */
    public function customFields()
    {
        $customFields = DealCustomField::where('type', 'select')
            ->where('active', true) // Asumiendo que tienes un campo 'active'
            ->get(['id', 'name', 'label', 'options']);
            
        return response()->json($customFields);
    }
}