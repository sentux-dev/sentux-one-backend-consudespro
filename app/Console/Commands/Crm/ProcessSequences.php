<?php

namespace App\Console\Commands\Crm;

use Illuminate\Console\Command;
use App\Models\Crm\ContactSequenceEnrollment;
use App\Models\Crm\Task;
use Illuminate\Support\Carbon;

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

        // 1. Buscar todas las inscripciones activas y vencidas
        $enrollments = ContactSequenceEnrollment::where('status', 'active')
            ->where('next_step_due_at', '<=', now())
            ->get();

        if ($enrollments->isEmpty()) {
            $this->info('No hay pasos de secuencia pendientes para ejecutar.');
            return 0;
        }

        $this->info("Se encontraron {$enrollments->count()} pasos pendientes. Procesando...");

        foreach ($enrollments as $enrollment) {
            $sequence = $enrollment->sequence;
            // 2. Encontrar el siguiente paso a ejecutar basado en el orden
            $nextStep = $sequence->steps()->where('order', $enrollment->current_step + 1)->first();

            // Si no hay más pasos, la secuencia ha terminado
            if (!$nextStep) {
                $enrollment->update(['status' => 'completed', 'next_step_due_at' => null]);
                $this->info("Secuencia #{$sequence->id} completada para el contacto #{$enrollment->contact_id}.");
                continue; // Pasar a la siguiente inscripción
            }

            // 3. Ejecutar la acción del paso actual
            $this->executeStepAction($enrollment, $nextStep);

            // 4. Buscar el paso que sigue al actual para calcular la próxima fecha
            $stepAfterNext = $sequence->steps()->where('order', $nextStep->order + 1)->first();
            $nextDueDate = null;
            if ($stepAfterNext) {
                // La fecha siempre se calcula desde el momento de la inscripción original
                $nextDueDate = Carbon::parse($enrollment->enrolled_at)
                    ->add($stepAfterNext->delay_unit, $stepAfterNext->delay_amount);
            }

            // 5. Actualizar la inscripción con el nuevo estado
            $enrollment->update([
                'current_step' => $nextStep->order,
                'next_step_due_at' => $nextDueDate,
                'status' => $stepAfterNext ? 'active' : 'completed', // Si no hay más pasos, se completa
            ]);

            $this->info("Paso #{$nextStep->order} de la secuencia #{$sequence->id} ejecutado para el contacto #{$enrollment->contact_id}.");
        }

        $this->info('Procesamiento de secuencias finalizado.');
        return 0;
    }

    /**
     * Ejecuta la acción específica de un paso de la secuencia.
     */
    private function executeStepAction($enrollment, $step)
    {
        switch ($step->action_type) {
            case 'send_email_template':
                $this->info("-> Acción: Enviar email (plantilla #{$step->parameters['template_id']})");
                // Aquí iría tu lógica para enviar un correo usando una Mailable class.
                // Ejemplo: Mail::to($enrollment->contact->email)->send(new SequenceEmail($step->parameters['template_id']));
                break;

            case 'create_manual_task':
                $this->info("-> Acción: Crear tarea manual '{$step->parameters['description']}'");
                Task::create([
                    'contact_id' => $enrollment->contact_id,
                    'description' => $step->parameters['description'],
                    'action_type' => $step->parameters['task_type'],
                    'owner_id' => $enrollment->contact->owner_id, // Se asigna al propietario del contacto
                    'status' => 'pendiente',
                    'schedule_date' => now(), // La tarea se crea para ser hecha ahora
                ]);
                break;
        }
    }
}