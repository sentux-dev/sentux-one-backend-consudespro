<?php

namespace App\Console\Commands\Crm;

use App\Mail\SequenceEmail;
use Illuminate\Console\Command;
use App\Models\Crm\ContactSequenceEnrollment;
use App\Models\Crm\EmailTemplate;
use App\Models\Crm\Task;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

class ProcessSequences extends Command
{
    /**
     * The name and signature of the console command.
     * @var string
     */
    protected $signature = 'crm:process-sequences';

    /**
     * The console command description.
     * @var string
     */
    protected $description = 'Procesa los pasos pendientes en las secuencias de automatización.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando procesamiento de secuencias...');

        // ✅ 1. Carga previa (Eager Loading) del contacto y su propietario.
        // Esto es mucho más eficiente y asegura que los datos siempre estén disponibles.
        $enrollments = ContactSequenceEnrollment::where('status', 'active')
            ->where('next_step_due_at', '<=', now())
            ->with('contact.owner') // Cargar el contacto y el propietario del contacto
            ->get();

        if ($enrollments->isEmpty()) {
            $this->info('No hay pasos de secuencia pendientes para ejecutar.');
            return 0;
        }

        $this->info("Se encontraron {$enrollments->count()} pasos pendientes. Procesando...");

        foreach ($enrollments as $enrollment) {
            $sequence = $enrollment->sequence;
            $nextStep = $sequence->steps()->where('order', $enrollment->current_step)->first();

            if (!$nextStep) {
                $enrollment->update(['status' => 'completed', 'next_step_due_at' => null]);
                $this->info("Secuencia #{$sequence->id} completada para el contacto #{$enrollment->contact_id}.");
                continue;
            }

            $this->executeStepAction($enrollment, $nextStep);

            $stepAfterNext = $sequence->steps()->where('order', $nextStep->order + 1)->first();
            $nextDueDate = null;
            if ($stepAfterNext) {
                $nextDueDate = Carbon::parse($enrollment->enrolled_at)
                    ->add($stepAfterNext->delay_unit, $stepAfterNext->delay_amount);
            }

            $enrollment->update([
                'current_step' => $nextStep->order + 1,
                'next_step_due_at' => $nextDueDate,
                'status' => $stepAfterNext ? 'active' : 'completed',
            ]);

            $this->info("Paso #{$nextStep->order} de la secuencia #{$sequence->id} ejecutado para el contacto #{$enrollment->contact_id}.");
        }

        $this->info('Procesamiento de secuencias finalizado.');
        return 0;
    }

    private function executeStepAction($enrollment, $step)
    {
        $contact = $enrollment->contact;
        if (!$contact) {
            $this->error("-> Error: No se encontró el contacto #{$enrollment->contact_id}. Omitiendo paso.");
            return;
        }

        switch ($step->action_type) {
            case 'send_email_template':
                $template = EmailTemplate::find($step->parameters['template_id']);
                if (!$template) {
                    $this->error("-> Plantilla #{$step->parameters['template_id']} no encontrada. Omitiendo paso.");
                    break;
                }

                $owner = $contact->owner;
                
                // ✅ 2. Lógica de reemplazo mejorada usando arrays
                $placeholders = [
                    '[CONTACT_FIRST_NAME]',
                    '[CONTACT_LAST_NAME]',
                    '[CONTACT_EMAIL]',
                    '[OWNER_NAME]',
                ];
                $replacements = [
                    $contact->first_name,
                    $contact->last_name,
                    $contact->email,
                    $contact->owner->name ?? '', // Accedemos al propietario a través del contacto
                ];

                $finalBody = str_replace($placeholders, $replacements, $template->body);
                $finalSubject = str_replace($placeholders, $replacements, $template->subject);
                
                Mail::to($contact->email)->send(new SequenceEmail($finalSubject, $finalBody, $owner));
                $this->info("-> Email enviado a {$contact->email} usando plantilla '{$template->name}'");
                break;

            case 'create_manual_task':
                Task::create([
                    'contact_id' => $contact->id,
                    'description' => $step->parameters['description'],
                    'action_type' => $step->parameters['task_type'],
                    'owner_id' => $contact->owner_id, // Usamos el owner_id del contacto
                    'status' => 'pendiente',
                    'schedule_date' => now(),
                ]);
                $this->info("-> Tarea manual '{$step->parameters['description']}' creada para el contacto.");
                break;
        }
    }
}