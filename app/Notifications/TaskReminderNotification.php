<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Crm\Task;

class TaskReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public Task $task;

    public function __construct(Task $task)
    {
        $this->task = $task;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * ✅ Define el formato de los datos que se guardarán en la columna 'data'
     * de la tabla 'notifications', ahora con más detalles.
     */
    public function toArray(object $notifiable): array
    {
        // Precargamos la relación de contacto si no ha sido cargada
        $this->task->loadMissing('contact');
        $contact = $this->task->contact;
        
        $contactName = $contact ? ($contact->first_name . ' ' . $contact->last_name) : 'Contacto no disponible';
        $scheduleTime = $this->task->schedule_date ? $this->task->schedule_date : 'Sin hora específica';

        return [
            'title'         => 'Recordatorio: ' . ($this->task->description ?: 'Tarea Pendiente'),
            'message'       => "Para: {$contactName}",
            'schedule_time' => $scheduleTime,
            'url'           => "/crm/contacts/{$this->task->contact_id}", // URL para navegar al contacto
            'task_id'       => $this->task->id, // ID de la tarea por si se necesita en el futuro
        ];
    }
}