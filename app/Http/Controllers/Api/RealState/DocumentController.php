<?php

namespace App\Http\Controllers\Api\RealState;

use App\Http\Controllers\Controller;
use App\Models\RealState\Lot;
use App\Models\RealState\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    /**
     * Lista todos los documentos de un lote específico.
     */
    public function index(Lot $lot)
    {
        return $lot->documents()->with('user:id,name')->latest()->get();
    }

    /**
     * Sube y guarda un nuevo documento para un lote.
     */
    public function store(Request $request, Lot $lot)
    {
        $request->validate([
            'document' => 'required|file|mimes:pdf,jpg,png,doc,docx|max:10240', // PDF, imagen o Word, máx 10MB
            'name' => 'required|string|max:255',
        ]);

        // Guardar el archivo en una carpeta específica para el proyecto y el lote
        $filePath = $request->file('document')->store(
            'real_state/projects/' . $lot->project->slug . '/lots/' . $lot->slug,
            'public' // Usar el disco 'public'
        );

        $document = $lot->documents()->create([
            'name' => $request->name,
            'file_path' => $filePath,
            'user_id' => Auth::id(),
        ]);

        return response()->json($document, 201);
    }

    /**
     * Elimina un documento.
     */
    public function destroy(Document $document)
    {
        // Eliminar el archivo físico del almacenamiento
        if (Storage::disk('public')->exists($document->file_path)) {
            Storage::disk('public')->delete($document->file_path);
        }

        // Eliminar el registro de la base de datos
        $document->delete();

        return response()->json(['message' => 'Documento eliminado correctamente.']);
    }
}