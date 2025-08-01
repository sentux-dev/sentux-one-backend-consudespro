<?php

namespace App\Http\Controllers\Api\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\ExternalLead;
use App\Models\Crm\Contact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LeadActionController extends Controller
{
    /**
     * Ejecuta una acción específica sobre un lead.
     */
    public function executeAction(Request $request, ExternalLead $externalLead)
    {
        $validated = $request->validate([
            'action' => 'required|string|in:create_contact', // Por ahora solo aceptamos esta acción
        ]);

        if ($externalLead->status !== 'pendiente') {
            return response()->json(['message' => 'Este lead ya ha sido procesado.'], 409);
        }

        switch ($validated['action']) {
            case 'create_contact':
                return $this->createContactFromLead($externalLead);
        }

        return response()->json(['message' => 'Acción no válida.'], 400);
    }

    /**
     * Lógica para crear un Contacto a partir de un Lead Externo.
     */
    private function createContactFromLead(ExternalLead $lead)
    {
        DB::beginTransaction();
        try {
            $payload = $lead->payload;

            // Mapeo de campos (puedes hacerlo más complejo en el futuro)
            $email = $payload['email'] ?? null;
            $firstName = $payload['first_name'] ?? $payload['name'] ?? 'Sin Nombre';
            $lastName = $payload['last_name'] ?? '';
            $phone = $payload['phone'] ?? $payload['phone_number'] ?? null;

            if (!$email) {
                throw new \Exception('El payload del lead no contiene un email.');
            }

            // Verificar si el contacto ya existe
            $contact = Contact::where('email', $email)->first();
            $actionTaken = 'CONTACT_UPDATED';

            if (!$contact) {
                $contact = Contact::create([
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $email,
                    'phone' => $phone,
                    'contact_status_id' => 1, // Asumimos 'Nuevo' como estado inicial
                    'owner_id' => 1, // Asignación por defecto, lo mejoraremos en Fase 3
                ]);
                $actionTaken = 'CONTACT_CREATED';
            }
            
            // Actualizar el estado del lead
            $lead->update([
                'status' => 'procesado',
                'processed_at' => now(),
            ]);

            // Crear un log del procesamiento
            $lead->processingLogs()->create([
                'action_taken' => $actionTaken,
                'details' => "Se creó/actualizó el contacto con ID: {$contact->id} desde la fuente: {$lead->source}",
                'snapshot' => $payload
            ]);

            DB::commit();

            return response()->json([
                'message' => "Lead procesado con éxito. Contacto ID: {$contact->id}",
                'contact' => $contact
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            $lead->update(['status' => 'error', 'error_message' => $e->getMessage()]);
            Log::error("Error al procesar lead ID {$lead->id}: " . $e->getMessage());

            return response()->json(['message' => 'Error al procesar el lead.'], 500);
        }
    }
}