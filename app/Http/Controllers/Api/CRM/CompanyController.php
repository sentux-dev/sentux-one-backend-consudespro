<?php

namespace App\Http\Controllers\Api\CRM;

use App\Http\Controllers\Controller;
use App\Models\Crm\Company;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CompanyController extends Controller
{
    /**
     * Muestra una lista de todas las empresas.
     */
    public function index(Request $request)
    {
        $query = Company::query();

        // Filtro de búsqueda global
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('industry', 'like', "%{$search}%")
                  ->orWhere('website', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Ordenamiento
        $sortField = $request->get('sort_field', 'name');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortField, $sortOrder);

        // Paginación
        $perPage = $request->get('per_page', 15);
        
        return $query->paginate($perPage);
    }

    /**
     * Guarda una nueva empresa en la base de datos.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:crm_companies,name',
            'industry' => 'nullable|string|max:255',
            'website' => 'nullable|url|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'country' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'owner_id' => 'nullable|exists:users,id'
        ]);

        $company = Company::create($validated);
        return response()->json($company, 201);
    }

    /**
     * Muestra una empresa específica.
     */
    public function show(Company $company)
    {
        return $company;
    }

    /**
     * Actualiza una empresa existente.
     */
    public function update(Request $request, Company $company)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('crm_companies')->ignore($company->id)],
            'industry' => 'nullable|string|max:255',
            'website' => 'nullable|url|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'country' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'owner_id' => 'nullable|exists:users,id'
        ]);

        $company->update($validated);
        return response()->json($company);
    }

    /**
     * Elimina una empresa.
     */
    public function destroy(Company $company)
    {
        // Lógica de seguridad: no permitir eliminar si tiene contactos o negocios asociados
        if ($company->contacts()->exists() || $company->deals()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar la empresa porque tiene contactos o negocios asociados.'
            ], 409); // 409 Conflict
        }

        $company->delete();
        return response()->json(['message' => 'Empresa eliminada correctamente.']);
    }
}