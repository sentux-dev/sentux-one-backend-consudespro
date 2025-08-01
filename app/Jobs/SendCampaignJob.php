<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\Email\EmailProviderManager;
use App\Models\Marketing\Campaign;
use App\Models\Crm\Contact;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class SendCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $failOnTimeout = true;
    public $tries = 3; // Reintentar hasta 3 veces si falla

    public function __construct(
        public Campaign $campaign,
        public Collection $contacts,
        public bool $isTest = false
    ){}

    public function handle(EmailProviderManager $emailManager): void
    {
        foreach ($this->contacts as $contact) {
            // Reemplazar placeholders como {{contact.first_name}}
            $htmlContent = str_replace('{{contact.first_name}}', $contact->first_name, $this->campaign->content_html);
            $htmlContent = str_replace('{{contact.last_name}}', $contact->last_name, $htmlContent);
            $htmlContent = str_replace('{{contact.email}}', $contact->email, $htmlContent);
            
            $log = null;
            if (!$this->isTest) {
                // Crear el registro de log antes de enviar
                $log = $this->campaign->emailLogs()->create([
                    'contact_id' => $contact->id,
                    'status' => 'enviado',
                ]);
            }

            // Enviar a través del manager
            $messageId = $emailManager->driver()->send(
                $contact->email,
                $this->campaign->subject,
                $htmlContent,
                $this->campaign->from_email,
                $this->campaign->from_name,
                $this->isTest ? [] : ['log_id' => $log->id] // Metadatos para el webhook
            );

            if ($messageId && !$this->isTest) {
                // Si el envío fue exitoso, guardamos el ID del proveedor
                $log->update(['provider_message_id' => $messageId]);
            } elseif (!$messageId && !$this->isTest) {
                // Si el envío falló, lo registramos
                $log->update([
                    'status' => 'fallido',
                    'error_message' => 'La API de correo no devolvió un ID de mensaje.'
                ]);
            }
        }
    }
    
    // Método que se ejecuta si el job falla permanentemente
    public function failed(\Throwable $exception): void
    {
        Log::error("Job de envío de campaña falló para la campaña {$this->campaign->id}", [
            'error' => $exception->getMessage()
        ]);
    }
}