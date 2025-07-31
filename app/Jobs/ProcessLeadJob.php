<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Crm\ExternalLead;
use App\Services\Crm\WorkflowProcessorService;
use App\Models\Crm\Contact;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessLeadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public ExternalLead $lead) {}

    public function handle(WorkflowProcessorService $processor): void
    {
        Log::info("Procesando lead {$this->lead->id} desde la fuente {$this->lead->source}");

        // 1. Encontrar un workflow que coincida
        $workflow = $processor->findMatchingWorkflow($this->lead);
        Log::info("Workflow encontrado: " . ($workflow ? $workflow->name : 'Ninguno'));

        if (!$workflow) {
            $this->logAction('NO_WORKFLOW_MATCH', 'No se encontró un workflow aplicable.');
            return;
        }
        
        $this->logAction('WORKFLOW_MATCHED', "Aplicando workflow: '{$workflow->name}' (ID: {$workflow->id})");

        // 2. Ejecutar las acciones del workflow
        DB::beginTransaction();
        try {
            foreach ($workflow->actions as $action) {
                $this->executeAction($action);
            }
            
            $this->lead->update(['status' => 'procesado', 'processed_at' => now()]);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $this->lead->update(['status' => 'error', 'error_message' => $e->getMessage()]);
            $this->logAction('ERROR', "Error ejecutando workflow: " . $e->getMessage());
            Log::error("Error procesando lead {$this->lead->id} con workflow {$workflow->id}: " . $e->getMessage());
        }
    }

    private function executeAction($action): void
    {
        switch ($action->action_type) {
            case 'create_contact':
                $this->createContactAction($action->parameters);
                break;
            // Aquí irían los casos para otras acciones: 'create_task', 'notify_user', etc.
        }
    }

    private function createContactAction(array $params): void
    {
        $payload = $this->lead->payload;
        $email = data_get($payload, 'email');

        if (!$email) {
            throw new \Exception("La acción 'create_contact' falló: el payload no contiene 'email'.");
        }

        // Evitar duplicados
        $contact = Contact::where('email', $email)->first();
        if ($contact) {
            $this->logAction('ACTION_SKIPPED', "La acción 'create_contact' se omitió porque el contacto con email '{$email}' ya existe.");
            return;
        }

        $contact = Contact::create([
            'first_name' => data_get($payload, 'first_name', 'Lead'),
            'last_name' => data_get($payload, 'last_name', '#' . $this->lead->id),
            'email' => $email,
            'phone' => data_get($payload, 'phone'),
            'contact_status_id' => $params['status_id'] ?? 1, // 'Nuevo' por defecto
            'owner_id' => $params['owner_id'] ?? 1, // Admin por defecto
        ]);

        $this->logAction('ACTION_EXECUTED', "Acción 'create_contact' ejecutada. Creado contacto con ID: {$contact->id}");
    }

    private function logAction(string $action, string $details): void
    {
        $this->lead->processingLogs()->create([
            'action_taken' => $action,
            'details' => $details,
        ]);
    }
}