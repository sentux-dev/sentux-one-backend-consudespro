<?php
namespace App\Http\Controllers\Api\CRM;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessLeadImportJob;
use App\Imports\LeadsImport;
use App\Models\Crm\ContactCustomField;
use App\Models\Crm\LeadImport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\HeadingRowImport;
use Illuminate\Support\Facades\Auth;

class LeadImportController extends Controller
{
    /**
     * Analiza el archivo subido para extraer sus encabezados y lo guarda temporalmente.
     */
    public function analyze(Request $request)
    {
        $request->validate(['file' => 'required|mimes:csv,xlsx,xls']);

        $file = $request->file('file');
        $headings = (new HeadingRowImport)->toArray($file)[0][0] ?? [];
        $path = $file->store('imports');

        // 🔹 Obtenemos los campos personalizados activos para el mapeo
        $customFields = ContactCustomField::where('active', true)->get(['id', 'name', 'label']);

        return response()->json([
            'headings' => $headings,
            'file_path' => $path,
            'custom_fields' => $customFields, // 🔹 Devolvemos los campos
        ]);
    }

    /**
     * Procesa el archivo previamente subido usando el mapeo de columnas.
     */
    public function process(Request $request)
    {
        // 🔹 Validación actualizada para la nueva estructura de mapeo
        $validated = $request->validate([
            'file_path' => 'required|string',
            'mappings' => 'required|array',
            'mappings.*.type' => 'required|in:column,static',
            'mappings.*.value' => 'nullable|string',
        ]);

        $leadImport = LeadImport::create([
            'user_id' => Auth::id(),
            'original_file_name' => $request->input('file_name', 'import.xlsx'),
            'mappings' => $validated['mappings'],
        ]);

        ProcessLeadImportJob::dispatch(
            $validated['file_path'],
            $validated['mappings'],
            Auth::id(),
            $leadImport->id
        );

        return response()->json(['message' => 'Tu importación ha comenzado y se procesará en segundo plano.']);
    }
}