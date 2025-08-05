<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    public string $token;

    public function __construct(string $token)
    {
        $this->token = $token;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $frontendUrl = config('app.frontend_url');
        // Construimos la URL completa para el frontend
        $url = "{$frontendUrl}/auth/reset-password?token={$this->token}&email={$notifiable->getEmailForPasswordReset()}";

        return (new MailMessage)
                    ->subject('Notificación de Restablecimiento de Contraseña')
                    ->line('Estás recibiendo este correo porque recibimos una solicitud de restablecimiento de contraseña para tu cuenta.')
                    ->action('Restablecer Contraseña', $url)
                    ->line('Este enlace de restablecimiento de contraseña expirará en 60 minutos.')
                    ->line('Si no solicitaste un restablecimiento de contraseña, no se requiere ninguna acción adicional.');
    }
}