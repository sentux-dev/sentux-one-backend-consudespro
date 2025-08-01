<?php
namespace App\Http\Controllers\Api\CRM;

use App\Http\Controllers\Controller;
use App\Imports\LeadsImport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\HeadingRowImport;

class LeadImportController extends Controller
{
    /**
     * Analiza el archivo subido para extraer sus encabezados y lo guarda temporalmente.
     */
    public function analyze(Request $request)
    {
        $request->validate(['file' => 'required|mimes:csv,xlsx,xls']);

        $file = $request->file('file');
        
        // Extraer solo los encabezados de la primera fila
        $headings = (new HeadingRowImport)->toArray($file)[0][0] ?? [];
        
        // Guardar el archivo temporalmente y devolver su ruta y encabezados
        $path = $file->store('imports');

        return response()->json([
            'headings' => $headings,
            'file_path' => $path,
        ]);
    }

    /**
     * Procesa el archivo previamente subido usando el mapeo de columnas.
     */
    public function process(Request $request)
    {
        $validated = $request->validate([
            'file_path' => 'required|string',
            'mappings' => 'required|array'
        ]);

        try {
            $import = new LeadsImport($validated['mappings']);
            Excel::import($import, $validated['file_path']);
            
            $leadsCreated = $import->getLeadsCreatedCount();

            return response()->json(['message' => "Importación completada. Se crearon {$leadsCreated} leads."]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Ocurrió un error al procesar el archivo: ' . $e->getMessage()], 500);
        }
    }
}