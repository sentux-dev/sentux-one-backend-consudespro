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
use App\Models\Crm\ContactCustomField;
use App\Models\Crm\Task;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\AssignmentCounter;

class ProcessLeadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected ?Contact $contact = null;

    private array $allowedStandardFields = [
        'first_name', 'last_name', 'email', 'phone', 'cellphone', 'country', 
        'city', 'state', 'address', 'occupation', 'job_position', 'birthdate'
    ];

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
            case 'set_field_value':
                $this->setFieldValueAction($action->parameters);
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
        $phone = data_get($payload, 'phone');

        if (empty($email) && empty($phone)) {
            throw new \Exception("La acción 'create_contact' falló: el payload no contiene 'email' ni 'phone'.");
        }

        if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->contact = Contact::firstOrCreate(
                ['email' => $email],
                [
                    'first_name' => data_get($payload, 'first_name', 'Lead'),
                    'last_name' => data_get($payload, 'last_name', '#' . $this->lead->id),
                    'phone' => $phone,
                    'contact_status_id' => $params['status_id'] ?? 1,
                ]
            );
            $logMessage = "Contacto creado/encontrado por email con ID: {$this->contact->id}";
        } 
        elseif (!empty($phone)) {
             $this->contact = Contact::firstOrCreate(
                ['phone' => $phone],
                [
                    'first_name' => data_get($payload, 'first_name', 'Lead'),
                    'last_name' => data_get($payload, 'last_name', '#' . $this->lead->id),
                    'email' => null,
                    'contact_status_id' => $params['status_id'] ?? 1,
                ]
            );
            $logMessage = "Contacto creado/encontrado por teléfono con ID: {$this->contact->id}";
        }
        
        if ($this->contact) {
            $this->saveCustomFields($this->lead->payload);
            $this->logAction('ACTION_EXECUTED', $logMessage);
        }
        else {
            $this->logAction('ACTION_SKIPPED', "No se pudo crear un contacto, no se proporcionó email ni teléfono.");
        }
    }
    
    private function setFieldValueAction(array $params): void
    {
        if (!$this->contact) {
            $this->logAction('ACTION_SKIPPED', "Se omitió 'set_field_value' porque no hay un contacto asociado.");
            return;
        }

        $fieldKey = data_get($params, 'field_key');
        $valueType = data_get($params, 'value_type', 'static');
        $fieldValue = null;
        $payloadKey = null; // ✅ Variable para logging

        if (empty($fieldKey)) {
            throw new \Exception("La acción 'set_field_value' requiere un 'field_key'.");
        }

        if ($valueType === 'payload') {
            $payloadKey = data_get($params, 'payload_key');
            if (empty($payloadKey)) {
                throw new \Exception("La acción 'set_field_value' con tipo 'payload' requiere un 'payload_key'.");
            }
            // ✅ Primero busca en el payload principal
            $fieldValue = data_get($this->lead->payload, $payloadKey);

            // ✅ Si no lo encuentra, busca en la fila original (fallback)
            if (is_null($fieldValue)) {
                $fieldValue = data_get($this->lead->payload, '_original_row.' . $payloadKey);
            }
        } else {
            $fieldValue = data_get($params, 'field_value');
        }

        // ✅ Condición de salida mejorada con el nuevo mensaje de log
        if (is_null($fieldValue)) {
            $logMessage = $valueType === 'payload'
                ? "Se omitió la asignación al campo '{$fieldKey}' porque la clave del payload '{$payloadKey}' no fue encontrada o su valor es nulo."
                : "Se omitió la asignación al campo '{$fieldKey}' porque el valor estático proporcionado es nulo.";
            
            $this->logAction('ACTION_SKIPPED', $logMessage);
            return;
        }
        
        if (str_starts_with($fieldKey, 'cf_')) {
            $slug = substr($fieldKey, 3);
            $customField = ContactCustomField::where('slug', $slug)->where('active', true)->first();

            if ($customField) {
                $this->contact->customFieldValues()->updateOrCreate(
                    ['custom_field_id' => $customField->id],
                    ['value' => $fieldValue]
                );
                $this->logAction('ACTION_EXECUTED', "Campo personalizado '{$customField->name}' actualizado a '{$fieldValue}' para el contacto ID: {$this->contact->id}");
            } else {
                $this->logAction('ACTION_SKIPPED', "No se encontró un campo personalizado activo con el slug: '{$slug}'.");
            }
        } 
        else {
            if (in_array($fieldKey, $this->allowedStandardFields)) {
                $this->contact->{$fieldKey} = $fieldValue;
                $this->contact->save();
                $this->logAction('ACTION_EXECUTED', "Campo '{$fieldKey}' actualizado a '{$fieldValue}' para el contacto ID: {$this->contact->id}");
            } else {
                $this->logAction('ACTION_SKIPPED', "El campo '{$fieldKey}' no es un campo estándar permitido para modificación.");
            }
        }
    }

    private function saveCustomFields(array $payload): void
    {
        if (!$this->contact || empty($payload['_custom_fields'])) {
            return;
        }

        foreach ($payload['_custom_fields'] as $fieldName => $value) {
            $field = ContactCustomField::where('name', $fieldName)->first();
            if ($field) {
                $this->contact->customFieldValues()->updateOrCreate(
                    ['custom_field_id' => $field->id],
                    ['value' => $value]
                );
            }
        }
    }

    private function assignOwnerAction(array $params): void
    {
        if (!$this->contact) {
            $this->logAction('ACTION_SKIPPED', "Se omitió 'assign_owner' porque no hay un contacto asociado.");
            return;
        }

        $assigneeId = null;
        $assignmentType = $params['assignment_type'] ?? 'user';
        if ($assignmentType === 'user') {
            $assigneeId = $params['user_id'] ?? null;
        } else {
            $groupId = $params['group_id'] ?? null;
            $group = UserGroup::with('users')->find($groupId);
            
            if ($group && $group->users->isNotEmpty()) {
                $scope = $params['assignment_scope'] ?? 'group';
                $countable = ($scope === 'group') ? $group : $this->lead->workflow;

                DB::transaction(function () use ($countable, $group, &$assigneeId) {
                    $counter = AssignmentCounter::firstOrCreate([
                        'countable_id' => $countable->id,
                        'countable_type' => get_class($countable),
                    ]);

                    $nextIndex = ($counter->last_assigned_user_index + 1) % $group->users->count();
                    $assignee = $group->users[$nextIndex];
                    $assigneeId = $assignee->id;

                    $counter->update(['last_assigned_user_index' => $nextIndex]);
                });
            }
        }

        if ($assigneeId) {
            $this->contact->owner_id = $assigneeId;
            $this->contact->save();
            $this->logAction('ACTION_EXECUTED', "Se asignó el propietario ID: {$assigneeId} al contacto.");
        } else {
             $this->logAction('ACTION_SKIPPED', "No se pudo asignar propietario, no se encontró un usuario válido.");
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
            'assigned_to' => $this->contact->owner_id,
        ]);
        $this->logAction('ACTION_EXECUTED', "Se creó una tarea para el contacto ID: {$this->contact->id}");
    }

    private function notifyUserAction(array $params): void
    {
        $userId = $params['user_id'] ?? null;
        if (!$userId) return;

        Log::info("NOTIFICACIÓN: Notificar al usuario ID {$userId} sobre el lead ID {$this->lead->id}.");
        $this->logAction('ACTION_EXECUTED', "Se envió una notificación al usuario ID: {$userId}");
    }

    private function logAction(string $action, string $details): void
    {
        $this->lead->processingLogs()->create(['action_taken' => $action, 'details' => $details]);
    }
}