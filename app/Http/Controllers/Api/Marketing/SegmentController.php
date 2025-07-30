<?php

namespace App\Http\Controllers\Api\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Marketing\Segment;
use Illuminate\Http\Request;

class SegmentController extends Controller
{
    /**
     * Devuelve el conteo de contactos que coinciden con un conjunto de filtros.
     * Útil para la previsualización en el frontend.
     */
    public function preview(Request $request)
    {
        $filters = $request->validate(['filters' => 'required|array']);

        // Creamos una instancia temporal del segmento sin guardarla en BD
        $segment = new Segment(['filters' => $filters['filters']]);
        
        // Usamos el nuevo método para obtener la consulta y contamos los resultados
        $count = $segment->getContactsQuery()->count();

        return response()->json(['count' => $count]);
    }

    // --- Aquí irían los métodos CRUD para guardar/editar los segmentos ---
    
    public function index()
    {
         $segments = Segment::latest()->get();

        // 🔹 Añadimos el conteo de contactos a cada segmento
        $segments->each(function ($segment) {
            $segment->contacts_count = $segment->getContactsQuery()->count();
        });

        return $segments;
    }
    
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:marketing_segments,name',
            'description' => 'nullable|string',
            'filters' => 'required|array'
        ]);

        $segment = Segment::create($validated);
        
        return response()->json($segment, 201);
    }
}