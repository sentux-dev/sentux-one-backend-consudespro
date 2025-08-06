<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Crm\LeadImport; // Importar el modelo

class LeadImportCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected LeadImport $leadImport;
    protected ?\Throwable $exception;

    /**
     * Create a new notification instance.
     * Pasamos el lote de importación y una excepción opcional (en caso de fallo).
     */
    public function __construct(LeadImport $leadImport, \Throwable $exception = null)
    {
        $this->leadImport = $leadImport;
        $this->exception = $exception;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Construye el mensaje de correo.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $frontendUrl = config('app.frontend_url', url('/'));
        $inboxUrl = "{$frontendUrl}/crm/leads-inbox";

        // Si hubo una excepción, el job falló.
        if ($this->exception) {
            return (new MailMessage)
                ->error()
                ->subject('Error en la Importación de Leads')
                ->line("Hubo un error al procesar tu archivo '{$this->leadImport->original_file_name}'.")
                ->line('El equipo técnico ha sido notificado.')
                ->line("Error: " . $this->exception->getMessage());
        }

        // Si no, el job fue exitoso.
        return (new MailMessage)
                    ->subject('¡Importación de Leads Completada!')
                    ->line("Tu importación del archivo '{$this->leadImport->original_file_name}' ha finalizado.")
                    ->line("Se procesaron {$this->leadImport->total_rows} filas y se crearon exitosamente {$this->leadImport->imported_count} leads nuevos.")
                    ->action('Ver Bandeja de Entrada de Leads', $inboxUrl)
                    ->line('¡Gracias por usar nuestra aplicación!');
    }
}