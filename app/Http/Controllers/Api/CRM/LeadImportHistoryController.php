<?php
namespace App\Http\Controllers\Api\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\LeadImport;
use Illuminate\Http\Request;

class LeadImportHistoryController extends Controller
{
    public function index()
    {
        return LeadImport::with('user:id,name')->latest()->paginate(20);
    }

    public function destroy(LeadImport $leadImport)
    {
        // Al borrar el lote, la BD se encarga de borrar en cascada
        // los leads asociados gracias a la configuración de la migración.
        $leadImport->delete();

        return response()->json(['message' => 'Lote de importación y todos sus leads han sido eliminados.']);
    }
}