<?php
namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SequenceEmail extends Mailable
{
    use Queueable, SerializesModels;
    public string $emailSubject;
    public string $emailBody;
    public ?User $sender;

    public function __construct(string $subject, string $body, ?User $sender = null)
    {
        $this->emailSubject = $subject;
        $this->emailBody = $body;
        $this->sender = $sender;
    }

    public function build()
    {
        $mail = $this->subject($this->emailSubject)
                     ->html($this->emailBody);

        // ✅ Si se proporcionó un remitente, se establece como el "From" del correo.
        if ($this->sender && $this->sender->email) {
            $mail->from($this->sender->email, $this->sender->name);
        }
        // Si no se proporciona un remitente, Laravel usará la configuración por defecto de .env (MAIL_FROM_ADDRESS)

        return $mail;
    }
}