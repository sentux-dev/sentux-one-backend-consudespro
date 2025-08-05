<?php

namespace App\Http\Controllers\Api\CRM;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessLeadJob;
use App\Models\Crm\ExternalLead;
use Illuminate\Http\Request;


class LeadActionController extends Controller
{
    /**
     * Ejecuta una acción específica sobre un lead.
     */
    public function executeAction(Request $request, ExternalLead $externalLead)
    {
        // Validamos que la acción sea la que esperamos desde el frontend.
        $validated = $request->validate([
            'action' => 'required|string|in:process_workflow',
        ]);

        if ($externalLead->status !== 'pendiente') {
            return response()->json(['message' => 'Este lead ya ha sido procesado o está en cola.'], 409);
        }

        if ($validated['action'] === 'process_workflow') {
            // 🔹 Aquí está la magia: despachamos el job a la cola.
            ProcessLeadJob::dispatch($externalLead);
            $externalLead->update(['status' => 'procesado']);

            
            return response()->json(['message' => 'El lead ha sido encolado para su procesamiento por el workflow.']);
        }

        return response()->json(['message' => 'Acción no válida.'], 400);
    }

    public function bulkExecuteAction(Request $request)
    {
        $validated = $request->validate([
            'action' => 'required|string|in:process_workflow',
            'lead_ids' => 'required|array',
            'lead_ids.*' => 'integer|exists:crm_external_leads,id',
        ]);

        $leads = ExternalLead::whereIn('id', $validated['lead_ids'])
            ->where('status', 'pendiente') // Solo procesamos los que están pendientes
            ->get();

        foreach ($leads as $lead) {
            ProcessLeadJob::dispatch($lead);
            $lead->update(['status' => 'procesado']); // Actualizamos el estado para feedback inmediato
        }
        
        $count = $leads->count();
        return response()->json(['message' => "{$count} leads han sido encolados para su procesamiento."]);
    }

    public function processAllPending(Request $request)
    {
        // Obtenemos una consulta de todos los leads pendientes
        $pendingLeadsQuery = ExternalLead::where('status', 'pendiente');
        
        // Contamos cuántos son para el mensaje de respuesta
        $count = $pendingLeadsQuery->count();

        if ($count === 0) {
            return response()->json(['message' => 'No hay leads pendientes para procesar.']);
        }

        // Actualizamos el estado de todos a 'enviando' de una sola vez para feedback inmediato
        $pendingLeadsQuery->update(['status' => 'procesado']);

        // Volvemos a obtener la consulta (ya que el update la ejecuta) y despachamos los jobs en lotes
        ExternalLead::where('status', 'procesado')->chunkById(200, function ($leads) {
            foreach ($leads as $lead) {
                ProcessLeadJob::dispatch($lead);
            }
        });

        return response()->json(['message' => "Se han encolado {$count} leads para su procesamiento."]);
    }
}