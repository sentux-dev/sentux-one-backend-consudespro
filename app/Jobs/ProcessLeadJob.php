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
use App\Models\Crm\Workflow;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\AssignmentCounter;
use App\Models\Crm\ContactEntryHistory;
use App\Models\Crm\Campaign;
use App\Models\Crm\ContactSequenceEnrollment;
use App\Models\Crm\Origin;
use App\Models\Crm\Sequence;
use Illuminate\Support\Arr;

class ProcessLeadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected ?Contact $contact = null;

    // ✅ Nuevos tipos de acciones de transformación
    const TRANSFORMATION_ACTIONS = [
        'rename_field',
        'set_payload_field',
    ];

    private array $allowedStandardFields = [
        'first_name', 'last_name', 'email', 'phone', 'cellphone', 'country', 
        'city', 'state', 'address', 'occupation', 'job_position', 'birthdate'
    ];

    public function __construct(public ExternalLead $lead) {}

    /**
     * ✅ MÉTODO HANDLE REFACTORIZADO CON LÓGICA DE DOS FASES
     */
    public function handle(WorkflowProcessorService $processor): void
    {
        // 1. Encontrar un workflow que coincida con el PAYLOAD ORIGINAL.
        $workflow = $processor->findMatchingWorkflow($this->lead);

        if (!$workflow) {
            $this->logAction('NO_WORKFLOW_MATCH', 'No se encontró un workflow aplicable.');
            return;
        }
        
        $this->logAction('WORKFLOW_MATCHED', "Aplicando workflow: '{$workflow->name}'");

        DB::beginTransaction();
        try {
            // 2. Aplicar las transformaciones de ESE workflow al payload en memoria.
            $this->applyWorkflowTransformations($workflow);
            
            // 3. AHORA, buscar o crear la campaña/origen usando el payload TRANSFORMADO.
            $this->resolveSourceIds();

            // 4. Ejecutar las acciones de ejecución (crear contacto, etc.).
            foreach ($workflow->actions->whereNotIn('action_type', self::TRANSFORMATION_ACTIONS) as $action) {
                $this->executeExecutionAction($action);
            }
            
            // 5. Guardar el estado final del lead y su payload modificado.
            $this->lead->status = 'procesado';
            $this->lead->processed_at = now();
            $this->lead->save();

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $this->lead->update(['status' => 'error', 'error_message' => $e->getMessage()]);
            $this->logAction('ERROR', "Error ejecutando workflow: " . $e->getMessage());
            Log::error("Error procesando lead {$this->lead->id}: " . $e->getMessage());
        }
    }

    /**
     * ✅ Nuevo método que aplica las transformaciones de un workflow específico.
     */
    private function applyWorkflowTransformations(Workflow $workflow): void
    {
        $payload = $this->lead->payload;
        
        // Obtenemos solo las acciones de transformación de ESTE workflow, en su orden correcto.
        $transformationActions = $workflow->actions()->whereIn('action_type', self::TRANSFORMATION_ACTIONS)->get();

        foreach ($transformationActions as $action) {
            $params = $action->parameters;
            switch ($action->action_type) {
                case 'rename_field':
                    $originalKey = data_get($params, 'original_key');
                    $newKey = data_get($params, 'new_key');
                    if ($originalKey && $newKey && Arr::has($payload, $originalKey)) {
                        $value = Arr::get($payload, $originalKey);
                        Arr::set($payload, $newKey, $value);
                        Arr::forget($payload, $originalKey);
                        $this->logAction('PAYLOAD_TRANSFORMED', "Campo '{$originalKey}' renombrado a '{$newKey}'.");
                    }
                    break;
                
                case 'set_payload_field':
                    $key = data_get($params, 'field_key');
                    $value = data_get($params, 'field_value');
                    if ($key) {
                        Arr::set($payload, $key, $value);
                        $this->logAction('PAYLOAD_TRANSFORMED', "Valor del campo '{$key}' establecido.");
                    }
                    break;
            }
        }
        
        // Actualizamos el payload en la instancia del lead para la siguiente fase
        $this->lead->payload = $payload;
    }

    private function resolveSourceIds(): void
    {
        $payload = $this->lead->payload;
        $campaignId = null;
        $originId = null;

        if ($campaignName = data_get($payload, 'campaign')) {
            $campaign = Campaign::firstOrCreate(
                ['name' => trim($campaignName)],
                ['active' => true, 'order' => 999]
            );
            $campaignId = $campaign->id;
        }

        if ($originName = data_get($payload, 'origin')) {
            $origin = Origin::firstOrCreate(
                ['name' => trim($originName)],
                ['active' => true, 'order' => 999]
            );
            $originId = $origin->id;
        }
        
        data_set($payload, '_meta.campaign_id', $campaignId);
        data_set($payload, '_meta.origin_id', $originId);
        $this->lead->payload = $payload;
    }

    private function executeExecutionAction($action): void
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
            case 'enroll_in_sequence':
                $this->enrollInSequenceAction($action->parameters);
                break;
        }
    }

    private function enrollInSequenceAction(array $params): void
    {
        if (!$this->contact) {
            $this->logAction('ACTION_SKIPPED', "Se omitió 'enroll_in_sequence' porque no hay un contacto asociado.");
            return;
        }

        $sequenceId = data_get($params, 'sequence_id');
        if (!$sequenceId) return;

        // Inscribir al contacto (lógica similar a la del ContactController)
        $firstStep = Sequence::find($sequenceId)->steps()->orderBy('order')->first();
        $nextStepDueAt = null;
        if ($firstStep) {
            $nextStepDueAt = now()->add($firstStep->delay_unit, $firstStep->delay_amount);
        }

        ContactSequenceEnrollment::create([
            'contact_id' => $this->contact->id,
            'sequence_id' => $sequenceId,
            'enrolled_at' => now(),
            'status' => 'active',
            'current_step' => 0,
            'next_step_due_at' => $nextStepDueAt,
        ]);

        $this->logAction('ACTION_EXECUTED', "Contacto ID: {$this->contact->id} inscrito en la secuencia ID: {$sequenceId}");
    }

    private function createContactAction(array $params): void
    {
        $payload = $this->lead->payload;
        $email = data_get($payload, 'email');
        $phone = data_get($payload, 'phone');

        if (empty($email) && empty($phone)) {
            throw new \Exception("La acción 'create_contact' falló: el payload no contiene 'email' ni 'phone'.");
        }

        $logMessage = ''; // Inicializamos la variable
        $contactExists = false;

        if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {

            $existingContact = Contact::where('email', $email)->first();
            $contactExists = (bool)$existingContact;

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

            $existingContact = Contact::where('phone', $phone)->first();
            $contactExists = (bool)$existingContact;
            
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
            // --- LÓGICA DE HISTORIAL CORREGIDA ---
            // 1. Si es un reingreso, desmarcamos la entrada anterior como 'is_last'
            if ($contactExists) {
                ContactEntryHistory::where('contact_id', $this->contact->id)
                    ->where('is_last', true)
                    ->update(['is_last' => false]);
            }
            
            // 2. Creamos la nueva entrada del historial
            ContactEntryHistory::create([
                'contact_id'       => $this->contact->id,
                'entry_at'         => now(),
                'origin_id'        => data_get($payload, '_meta.origin_id'),
                'campaign_id'      => data_get($payload, '_meta.campaign_id'),
                'external_lead_id' => $this->lead->id,
                'details'          => $payload,
                // Si el contacto fue recién creado, es la original. Siempre es la última.
                'is_original'      => $this->contact->wasRecentlyCreated,
                'is_last'          => true,
            ]);
            // --- FIN DE LA LÓGICA ---

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
            $customField = ContactCustomField::where('name', $slug)->where('active', true)->first();

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