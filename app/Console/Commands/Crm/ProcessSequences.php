<?php

namespace App\Console\Commands\Crm;

use App\Mail\SequenceEmail;
use Illuminate\Console\Command;
use App\Models\Crm\ContactSequenceEnrollment;
use App\Models\Crm\EmailTemplate;
use App\Models\Marketing\EmailLog;
use App\Models\Crm\Activity;
use App\Services\Email\EmailProviderManager;
use App\Models\Crm\Task;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

class ProcessSequences extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crm:process-sequences';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Procesa los pasos pendientes y pre-genera tareas manuales en las secuencias.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando procesamiento de secuencias...');

        // FASE 1: Pre-generar tareas manuales que vencen en las próximas 24 horas.
        $this->generateUpcomingManualTasks();

        // FASE 2: Ejecutar pasos que vencen ahora (principalmente correos).
        $this->executeDueSteps();

        $this->info('Procesamiento de secuencias finalizado.');
        return 0;
    }

    /**
     * Busca y crea tareas manuales que están programadas para las próximas 24 horas.
     */
    private function generateUpcomingManualTasks()
    {
        $this->line('Buscando tareas manuales para generar anticipadamente...');

        $enrollments = ContactSequenceEnrollment::where('status', 'active')
            ->whereNotNull('next_step_due_at')
            ->whereBetween('next_step_due_at', [now(), now()->addDay()])
            ->with('contact.owner', 'sequence.steps')
            ->get();
            
        if ($enrollments->isEmpty()) {
            $this->line('-> No hay tareas manuales para pre-generar.');
            return;
        }

        $this->info("-> Se encontraron {$enrollments->count()} tareas manuales para pre-generar.");

        foreach ($enrollments as $enrollment) {
            $nextStep = $enrollment->sequence->steps->where('order', $enrollment->current_step)->first();
            $this->info("   - Procesando Contacto #{$enrollment->contact_id} para el paso #{$enrollment->current_step} (vencimiento: {$enrollment->next_step_due_at})");

            $this->info($nextStep->action_type);
            // Solo nos interesan las tareas manuales en esta fase
            if ($nextStep && $nextStep->action_type === 'create_manual_task') {
                $this->createManualTask($enrollment, $nextStep);
                $this->advanceSequence($enrollment, $nextStep); // Avanzamos la secuencia
            }
        }
    }

    /**
     * Ejecuta los pasos (como enviar correos) que ya están vencidos.
     */
    private function executeDueSteps()
    {
        $this->line('Buscando pasos vencidos para ejecutar ahora...');

        $enrollments = ContactSequenceEnrollment::where('status', 'active')
            ->where('next_step_due_at', '<=', now())
            ->with('contact.owner', 'sequence.steps')
            ->get();
        
        if ($enrollments->isEmpty()) {
            $this->line('-> No hay pasos inmediatos para ejecutar.');
            return;
        }

        $this->info("-> Se encontraron {$enrollments->count()} pasos inmediatos para ejecutar.");

        foreach ($enrollments as $enrollment) {
            $nextStep = $enrollment->sequence->steps->where('order', $enrollment->current_step)->first();

            if (!$nextStep) {
                $enrollment->update(['status' => 'completed', 'next_step_due_at' => null]);
                $this->info("   - Secuencia completada para el contacto #{$enrollment->contact_id}.");
                continue;
            }
            
            // Aquí solo ejecutamos acciones que no sean de creación de tareas manuales,
            // ya que esas se adelantan.
            if ($nextStep->action_type === 'send_email_template') {
                $this->sendSequenceEmail($enrollment, $nextStep);
            }
            // NOTA: Podríamos añadir un log o manejar el caso de una tarea manual que se "atrasó"
            // y no fue pre-generada, pero por ahora la lógica principal la cubre.
            
            $this->advanceSequence($enrollment, $nextStep);
        }
    }
    
    /**
     * Lógica para crear la tarea manual.
     */
    private function createManualTask($enrollment, $step)
    {
        $dueDate = Carbon::parse($enrollment->next_step_due_at);

        Task::create([
            'contact_id'    => $enrollment->contact_id,
            'description'   => $step->parameters['description'],
            'action_type'   => $step->parameters['task_type'],
            'owner_id'      => $enrollment->contact->owner_id,
            'status'        => 'pendiente',
            'created_by'    => null, // Tarea creada por el sistema
            'schedule_date' => $dueDate,
            'remember_date' => $dueDate->copy()->subMinutes(15), // Recordatorio 15 mins antes
        ]);

        // Crear la actividad

        Activity::create([
            'contact_id' => $enrollment->contact_id,
            'type' => 'tarea',
            'title' => ' [Tarea de Secuencia] ' .$step->parameters['description'],
            'description' => '[ ' . $step->parameters['task_type'] . ' ] ' . $step->parameters['description'],
            'created_by' => null, // Actividad creada por el sistema
        ]);

        $this->info("   - Tarea '{$step->parameters['description']}' creada para Contacto #{$enrollment->contact_id}. Vence: " . $dueDate->toDateTimeString());
    }

    /**
     * Lógica para enviar el correo de la secuencia.
     */
    private function sendSequenceEmail($enrollment, $step)
    {
        $contact = $enrollment->contact;
        $template = EmailTemplate::find($step->parameters['template_id']);

        if (!$contact || !$contact->email) {
            $this->error("   - Contacto #{$enrollment->contact_id} no tiene email. Omitiendo paso.");
            return;
        }

        if (!$template) {
            $this->error("   - Plantilla #{$step->parameters['template_id']} no encontrada. Omitiendo paso.");
            return;
        }

        $owner = $contact->owner;
        $placeholders = ['[CONTACT_FIRST_NAME]', '[CONTACT_LAST_NAME]', '[CONTACT_EMAIL]', '[OWNER_NAME]'];
        $replacements = [$contact->first_name, $contact->last_name, $contact->email, $owner->name ?? ''];

        $finalBody = str_replace($placeholders, $replacements, $template->body);
        $finalSubject = str_replace($placeholders, $replacements, $template->subject);

        // 1. Obtenemos el gestor de correo
        $emailManager = app(EmailProviderManager::class);

        // 2. Enviamos el correo UNA SOLA VEZ a través del gestor para obtener el ID de seguimiento
        $messageId = $emailManager->driver()->send(
            $contact->email,
            $finalSubject,
            $finalBody,
            $owner->email ?? config('mail.from.address'),
            $owner->name ?? config('mail.from.name')
        );

        if (!$messageId) {
            $this->error("   - Fallo al enviar correo a {$contact->email} a través del proveedor.");
            return; // Si el envío falla, no continuamos.
        }

        // 3. Creamos el registro de seguimiento (EmailLog)
        $emailLog = EmailLog::create([
            'contact_id' => $contact->id,
            'provider_message_id' => $messageId,
            'status' => 'enviado',
        ]);

        // 4. Creamos la actividad visible para el usuario y la enlazamos
        $emailTo = [];
        $emailTo[] = $contact->email;
        // pasar a texto y HTML
        $plainTextDescription = strip_tags($finalBody);


        Activity::create([
            'contact_id' => $contact->id,
            'email_log_id' => $emailLog->id,
            'type' => 'correo',
            'title' => '[Correo de Secuencia] - ' . $template->name,
            'description' => $plainTextDescription,
            'html_description' => $finalBody,
            'email_to' => $emailTo,
            'sender_email' => $owner->email ?? config('mail.from.address'),
            'sender_name' => $owner->name ?? config('mail.from.name'),
            'created_by' => null, // Actividad creada por el sistema
        ]);

        // ✅ CORRECCIÓN: Se eliminó el envío duplicado de abajo
        // Mail::to($contact->email)->send(new SequenceEmail($finalSubject, $finalBody, $owner));
        
        // ✅ CORRECCIÓN: Se actualizó el mensaje de log para ser más preciso
        $this->info("   - Email de secuencia enviado a {$contact->email} y registrado como actividad.");
    }

    /**
     * Actualiza la inscripción al siguiente paso.
     */
    private function advanceSequence($enrollment, $currentStep)
    {
        $stepAfter = $enrollment->sequence->steps->where('order', $currentStep->order + 1)->first();
        
        $nextDueDate = null;
        if ($stepAfter) {
            // La fecha del siguiente paso siempre se calcula desde la inscripción original.
            $nextDueDate = Carbon::parse($enrollment->enrolled_at)
                ->add($stepAfter->delay_unit, $stepAfter->delay_amount);
        }

        $enrollment->update([
            'current_step'     => $currentStep->order + 1,
            'next_step_due_at' => $nextDueDate,
            'status'           => $stepAfter ? 'active' : 'completed',
        ]);
    }
}