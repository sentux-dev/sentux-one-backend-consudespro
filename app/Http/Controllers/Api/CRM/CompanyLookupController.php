<?php

namespace App\Http\Controllers\Api\CRM;

use App\Http\Controllers\Controller;
use App\Models\Crm\Company;
use Illuminate\Http\Request;

class CompanyLookupController extends Controller
{
    /**
     * Busca empresas por nombre para el autocompletado del frontend.
     */
    public function search(Request $request)
    {
        $validated = $request->validate([
            'query' => 'nullable|string|max:255',
        ]);

        $query = $validated['query'] ?? '';

        $companies = Company::where('name', 'like', "%{$query}%")
            ->limit(10)
            ->get(['id', 'name']); // Devolvemos solo los campos necesarios

        return response()->json($companies);
    }
}