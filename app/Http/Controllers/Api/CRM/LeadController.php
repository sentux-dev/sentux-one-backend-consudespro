<?php

namespace App\Http\Controllers\Api\CRM;

use App\Http\Controllers\Controller;
use App\Models\Crm\ExternalLead;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    /**
     * Muestra una lista paginada de los leads externos.
     */
    public function index(Request $request)
    {
        // 🔹 --- LÓGICA DE PAGINACIÓN CORREGIDA --- 🔹
        $request->validate([
            'per_page' => 'integer|min:1|max:100',
            'status' => 'string|in:pendiente,procesado,error'
        ]);
        
        $query = ExternalLead::query();

        // Aquí puedes añadir filtros en el futuro (por source, status, etc.)
        if ($request->filled('source')) {
            $query->where('source', $request->source);
        }
        
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Usamos el valor 'per_page' de la solicitud, con un valor por defecto de 15.
        $perPage = $request->input('per_page', 15);

        return $query->latest('received_at')->paginate($perPage);
    }
}