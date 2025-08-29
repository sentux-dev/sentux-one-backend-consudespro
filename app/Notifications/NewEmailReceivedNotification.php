<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Crm\Activity; // Importamos el modelo Activity

class NewEmailReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public Activity $activity;

    /**
     * Creamos una nueva instancia de la notificación.
     *
     * @param Activity $activity La actividad del correo recién creado.
     */
    public function __construct(Activity $activity)
    {
        $this->activity = $activity;
    }

    /**
     * Definimos el canal de la notificación. En este caso, 'database'.
     */
    public function via(object $notifiable): array
    {
        return ['database']; // Igual que las notificaciones de tareas [cite: 1084]
    }

    /**
     * Damos formato a los datos que se guardarán en la base de datos.
     * La estructura es similar a la de las tareas para que el frontend la reconozca.
     */
    public function toArray(object $notifiable): array
    {
        // Aseguramos que la relación con el contacto esté cargada
        $this->activity->loadMissing('contact');
        $contact = $this->activity->contact;
        
        $contactName = $contact ? $contact->name : 'un contacto conocido';

        // Extraemos el asunto del título de la actividad
        $subject = str_replace('Correo Recibido: ', '', $this->activity->title);

        return [
            'title'   => "Nuevo Correo de: {$contactName}",
            'message' => $subject,
            'url'     => "/crm/contacts/{$this->activity->contact_id}", // URL para navegar al contacto [cite: 1088]
        ];
    }
}