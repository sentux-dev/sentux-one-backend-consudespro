<?php
namespace App\Http\Controllers\Api\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Marketing\Campaign;
use App\Models\Marketing\MailingList;
use App\Models\Marketing\Segment;
use App\Jobs\SendCampaignJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CampaignController extends Controller
{
    public function index()
    {
        // Aquí se puede añadir lógica para calcular estadísticas
        return Campaign::latest()->paginate();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:marketing_campaigns,name',
            'subject' => 'required|string|max:255',
            'from_name' => 'required|string|max:255',
            'from_email' => 'required|email|max:255',
            'content_html' => 'nullable|string',
            'template_id' => 'nullable|string',
            'segment_ids' => 'nullable|array',
            'list_ids' => 'nullable|array',
        ]);

        $campaign = Campaign::create($validated);

        // Sincronizar las relaciones
        if (!empty($validated['segment_ids'])) {
            $campaign->segments()->sync($validated['segment_ids']);
        }
        if (!empty($validated['list_ids'])) {
            $campaign->mailingLists()->sync($validated['list_ids']);
        }

        return response()->json($campaign->load(['segments', 'mailingLists']), 201);
    }

    public function show(Campaign $campaign)
    {
        // Cargar logs para el reporte
        $campaign->load(['segments', 'mailingLists', 'emailLogs.contact:id,first_name,last_name,email']);
        return $campaign;
    }

    public function update(Request $request, Campaign $campaign)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:marketing_campaigns,name,' . $campaign->id,
            'subject' => 'required|string|max:255',
            'from_name' => 'required|string|max:255',
            'from_email' => 'required|email|max:255',
            'content_html' => 'nullable|string',
            'template_id' => 'nullable|string',
            'segment_ids' => 'nullable|array',
            'list_ids' => 'nullable|array',
        ]);

        $campaign->update($validated);

        // Sincronizar las relaciones
        if ($request->has('segment_ids')) {
            $campaign->segments()->sync($validated['segment_ids']);
        }
        if ($request->has('list_ids')) {
            $campaign->mailingLists()->sync($validated['list_ids']);
        }

        return response()->json($campaign->load(['segments', 'mailingLists']));
    }

    public function send(Campaign $campaign)
    {
        $campaign->load(['segments.contacts', 'mailingLists.contacts']);

        $allContacts = collect();

        // Recolectar contactos de todos los segmentos asociados
        foreach ($campaign->segments as $segment) {
            // Usamos el método que ya construimos para obtener los contactos del segmento
            $contactsFromSegment = $segment->getContactsQuery()->get();
            $allContacts = $allContacts->merge($contactsFromSegment);
        }

        // Recolectar contactos de todas las listas asociadas
        foreach ($campaign->mailingLists as $list) {
            $allContacts = $allContacts->merge($list->contacts);
        }

        // Eliminar duplicados por ID de contacto
        $uniqueContacts = $allContacts->unique('id');

        if ($uniqueContacts->isEmpty()) {
            return response()->json(['message' => 'No se encontraron destinatarios en las audiencias seleccionadas.'], 422);
        }

        // Dividir en lotes y despachar los jobs
        $batchSize = config('services.mail_batch_size', 100);
        $contactChunks = $uniqueContacts->chunk($batchSize);

        foreach ($contactChunks as $chunk) {
            SendCampaignJob::dispatch($campaign, $chunk);
        }

        $campaign->update(['status' => 'enviando', 'sent_at' => now()]);

        return response()->json(['message' => 'La campaña ha sido encolada para su envío a ' . $uniqueContacts->count() . ' contactos.']);
    }

    public function sendTest(Request $request, Campaign $campaign)
    {
        $validated = $request->validate(['email' => 'required|email']);
        
        // Usamos el job pero con un solo contacto de prueba
        $testContact = (object)[
            'email' => $validated['email'],
            'first_name' => '[Nombre de Prueba]',
            'last_name' => '[Apellido de Prueba]',
        ];

        SendCampaignJob::dispatch($campaign, collect([$testContact]), true);

        return response()->json(['message' => 'Correo de prueba enviado a ' . $validated['email']]);
    }

    public function exportCsv(Campaign $campaign): StreamedResponse
    {
        $fileName = 'reporte-' . $campaign->slug . '-' . now()->format('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ];

        // Usamos una StreamedResponse para manejar grandes cantidades de datos sin agotar la memoria.
        return new StreamedResponse(function () use ($campaign) {
            $handle = fopen('php://output', 'w');

            // Añadir las cabeceras del CSV
            fputcsv($handle, [
                'ID Contacto',
                'Nombre',
                'Apellido',
                'Email',
                'Estado',
                'Fecha de Actividad',
            ]);

            // Obtener los logs en lotes para ser eficiente
            $campaign->emailLogs()->with('contact')->chunk(200, function ($logs) use ($handle) {
                foreach ($logs as $log) {
                    fputcsv($handle, [
                        $log->contact->id ?? 'N/A',
                        $log->contact->first_name ?? 'N/A',
                        $log->contact->last_name ?? 'N/A',
                        $log->contact->email ?? 'N/A',
                        $log->status,
                        $log->updated_at->format('Y-m-d H:i:s'),
                    ]);
                }
            });

            fclose($handle);
        }, 200, $headers);
    }
}