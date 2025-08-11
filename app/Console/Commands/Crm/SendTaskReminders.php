<?php

namespace App\Console\Commands\Crm;

use Illuminate\Console\Command;
use App\Models\Crm\Task;
use App\Notifications\TaskReminderNotification;

class SendTaskReminders extends Command
{
    protected $signature = 'crm:send-task-reminders';
    protected $description = 'Busca recordatorios de tareas pendientes y envía notificaciones a los usuarios.';

    public function handle()
    {
        $this->info('Buscando recordatorios de tareas pendientes...');

        // 1. Buscamos tareas que:
        //    - Estén pendientes.
        //    - Tengan una fecha de recordatorio.
        //    - La fecha de recordatorio ya haya pasado.
        //    - Aún no se les haya enviado una notificación.
        $tasksToRemind = Task::where('status', 'pendiente')
            ->whereNotNull('remember_date')
            ->where('remember_date', '<=', now())
            ->whereNull('reminder_sent_at')
            ->with('owner') // Cargar el propietario para notificarlo
            ->get();

        if ($tasksToRemind->isEmpty()) {
            $this->info('No hay recordatorios para enviar.');
            return 0;
        }

        $this->info("Se encontraron {$tasksToRemind->count()} recordatorios para enviar.");

        foreach ($tasksToRemind as $task) {
            // 2. Nos aseguramos de que la tarea tenga un propietario
            if ($task->owner) {
                // 3. Enviamos la notificación al propietario de la tarea
                $task->owner->notify(new TaskReminderNotification($task));

                // 4. Marcamos la tarea como notificada para no volver a enviarla
                $task->update(['reminder_sent_at' => now()]);
                
                $this->line("-> Notificación enviada a {$task->owner->email} por tarea #{$task->id}.");
            }
        }

        $this->info('Envío de recordatorios finalizado.');
        return 0;
    }
}