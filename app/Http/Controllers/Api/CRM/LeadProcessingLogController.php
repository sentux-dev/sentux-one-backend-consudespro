<?php

namespace App\Http\Controllers\Api\CRM;

use App\Http\Controllers\Controller;
use App\Models\Crm\LeadProcessingLog;
use Illuminate\Http\Request;

class LeadProcessingLogController extends Controller
{
    /**
     * Muestra una lista paginada y filtrable de los logs de procesamiento de leads.
     */
    public function index(Request $request)
    {
        $request->validate([
            'per_page' => 'integer|min:1|max:100',
            'search' => 'nullable|string|max:255',
        ]);

        $query = LeadProcessingLog::query()->with('externalLead');

        // Filtro para buscar por un lead específico (email, nombre, etc.)
        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->whereHas('externalLead', function ($q) use ($searchTerm) {
                $q->where('payload->email', 'like', "%{$searchTerm}%")
                  ->orWhere('payload->name', 'like', "%{$searchTerm}%")
                  ->orWhere('payload->first_name', 'like', "%{$searchTerm}%");
            });
        }

        $perPage = $request->input('per_page', 15);

        return $query->latest()->paginate($perPage);
    }
}