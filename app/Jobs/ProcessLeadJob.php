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
use App\Models\Crm\Task; // Importar Task
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache; // Importar Cache

class ProcessLeadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Propiedad para mantener el contexto del contacto a través de las acciones
    protected ?Contact $contact = null;

    public function __construct(public ExternalLead $lead) {}

    public function handle(WorkflowProcessorService $processor): void
    {
        $workflow = $processor->findMatchingWorkflow($this->lead);
        if (!$workflow) {
            $this->logAction('NO_WORKFLOW_MATCH', 'No se encontró un workflow aplicable.');
            return;
        }
        
        $this->logAction('WORKFLOW_MATCHED', "Aplicando workflow: '{$workflow->name}'");

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
            Log::error("Error procesando lead {$this->lead->id}: " . $e->getMessage());
        }
    }

    private function executeAction($action): void
    {
        switch ($action->action_type) {
            case 'create_contact':
                $this->createContactAction($action->parameters);
                break;
            case 'assign_owner':
                $this->assignOwnerAction($action->parameters);
                break;
            case 'create_task':
                $this->createTaskAction($action->parameters);
                break;
            case 'notify_user':
                $this->notifyUserAction($action->parameters);
                break;
        }
    }

    private function createContactAction(array $params): void
    {
        $payload = $this->lead->payload;
        $email = data_get($payload, 'email');

        if (!$email) {
            throw new \Exception("La acción 'create_contact' falló: el payload no contiene 'email'.");
        }

        // Busca o crea el contacto y lo guarda en la propiedad de la clase
        $this->contact = Contact::firstOrCreate(
            ['email' => $email],
            [
                'first_name' => data_get($payload, 'first_name', 'Lead'),
                'last_name' => data_get($payload, 'last_name', '#' . $this->lead->id),
                'phone' => data_get($payload, 'phone'),
                'contact_status_id' => $params['status_id'] ?? 1,
                'owner_id' => 1, // Propietario inicial por defecto (Admin)
            ]
        );
        $this->logAction('ACTION_EXECUTED', "Contacto creado/encontrado con ID: {$this->contact->id}");
    }

    private function assignOwnerAction(array $params): void
    {
        if (!$this->contact) {
            $this->logAction('ACTION_SKIPPED', "Se omitió 'assign_owner' porque no se ha creado un contacto.");
            return;
        }

        $assigneeId = null;
        $assignmentType = $params['assignment_type'] ?? 'user'; // 'user' o 'group'
        
        if ($assignmentType === 'user') {
            $assigneeId = $params['user_id'] ?? null;
        } else { // group
            $groupId = $params['group_id'] ?? null;
            $group = UserGroup::with('users')->find($groupId);
            
            if ($group && $group->users->isNotEmpty()) {
                $cacheKey = 'last_assigned_user_index_for_group_' . $groupId;
                $lastIndex = Cache::get($cacheKey, -1);
                $nextIndex = ($lastIndex + 1) % $group->users->count();
                
                $assigneeId = $group->users[$nextIndex]->id;
                Cache::put($cacheKey, $nextIndex, now()->addDay()); // Guardar el índice por un día
            }
        }

        if ($assigneeId) {
            $this->contact->owner_id = $assigneeId;
            $this->contact->save();
            $this->logAction('ACTION_EXECUTED', "Se asignó el propietario ID: {$assigneeId} al contacto.");
        }
    }
    
    private function createTaskAction(array $params): void
    {
        if (!$this->contact) {
            $this->logAction('ACTION_SKIPPED', "Se omitió 'create_task' porque no se ha creado un contacto.");
            return;
        }

        Task::create([
            'contact_id' => $this->contact->id,
            'description' => $params['description'] ?? 'Tarea automática de workflow',
            'due_date' => now()->addDays($params['due_in_days'] ?? 1),
            'assigned_to' => $this->contact->owner_id, // Asignar al propietario actual del contacto
        ]);

        $this->logAction('ACTION_EXECUTED', "Se creó una tarea para el contacto ID: {$this->contact->id}");
    }

    private function notifyUserAction(array $params): void
    {
        $userId = $params['user_id'] ?? null;
        if (!$userId) return;

        // Aquí iría la lógica real de notificación (ej: enviar un correo o una notificación de Laravel)
        // Por ahora, lo simulamos con un log.
        Log::info("NOTIFICACIÓN: Notificar al usuario ID {$userId} sobre el lead ID {$this->lead->id}.");
        $this->logAction('ACTION_EXECUTED', "Se envió una notificación al usuario ID: {$userId}");
    }

    private function logAction(string $action, string $details): void
    {
        $this->lead->processingLogs()->create(['action_taken' => $action, 'details' => $details]);
    }
}