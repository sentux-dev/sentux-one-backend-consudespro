<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use App\Models\UserEmailAccount;
use App\Models\Crm\Activity;
use App\Models\Crm\ActivityAttachment;
use App\Models\Marketing\EmailLog;
use Throwable;

// Clases de Symfony Mailer
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Email as SymfonyEmail;
use Symfony\Component\Mime\Address;

class SendEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Propiedades para almacenar toda la información del correo
    protected int $userId;
    protected int $accountId;
    protected int $contactId;
    protected array $emailData;

    /**
     * El constructor recibe todos los datos necesarios para enviar el correo.
     * @param int $userId ID del usuario que envía el correo.
     * @param int $accountId ID de la cuenta de correo a usar.
     * @param int $contactId ID del contacto asociado.
     * @param array $emailData Contiene 'to', 'cc', 'bcc', 'subject', 'body', 'attachments', y opcionalmente 'parent_activity_id' y 'reply_mode'.
     */
    public function __construct(int $userId, int $accountId, int $contactId, array $emailData)
    {
        $this->userId = $userId;
        $this->accountId = $accountId;
        $this->contactId = $contactId;
        $this->emailData = $emailData;
    }

    /**
     * El método handle() es donde se ejecuta la lógica principal del Job.
     */
    public function handle(): void
    {
        // Obtenemos los modelos necesarios a partir de los IDs
        $user = User::findOrFail($this->userId);
        $account = UserEmailAccount::findOrFail($this->accountId);
        $parentActivity = isset($this->emailData['parent_activity_id']) ? Activity::find($this->emailData['parent_activity_id']) : null;

        // Construimos el transporte SMTP y el Mailer
        $transport = $this->buildTransport($account);
        $mailer = new Mailer($transport);

        // Construimos el mensaje de correo
        $fromEmail = $account->email_address ?? $account->smtp_username;
        $fromName = $this->emailData['from_name'] ?? $user->name;
        $from = new Address($fromEmail, $fromName);

        $message = (new SymfonyEmail())
            ->from($from)
            ->subject($this->emailData['subject'])
            ->html($this->emailData['body']);

        // Añadimos destinatarios
        foreach ($this->emailData['to'] as $to) { $message->addTo(new Address($to)); }
        foreach ($this->emailData['cc'] ?? [] as $cc) { $message->addCc(new Address($cc)); }
        foreach ($this->emailData['bcc'] ?? [] as $bcc) { $message->addBcc(new Address($bcc)); }

        // Lógica específica para respuestas
        if ($parentActivity) {
            $this->addReplyHeaders($message, $parentActivity);
        }
        
        // Adjuntamos archivos desde sus rutas temporales
        foreach ($this->emailData['attachments'] ?? [] as $attachment) {
            $message->attachFromPath(
                Storage::path($attachment['path']),
                $attachment['original_name']
            );
        }

        // Usamos una transacción para asegurar la integridad de los datos
        DB::beginTransaction();
        try {
            // 1. Enviar el correo
            $mailer->send($message);
            $messageId = $message->getHeaders()->get('Message-ID')?->getBody();

            // 2. Crear el registro en EmailLog
            $emailLog = EmailLog::create([
                'user_id' => $user->id,
                'contact_id' => $this->contactId,
                'to' => $this->emailData['to'],
                'cc' => $this->emailData['cc'] ?? [],
                'bcc' => $this->emailData['bcc'] ?? [],
                'subject' => $this->emailData['subject'],
                'body' => $this->emailData['body'],
                'status' => 'enviado',
                'provider' => 'smtp',
                'provider_message_id' => $messageId,
                'meta' => ['from' => $fromEmail, 'from_name' => $fromName, 'account' => $account->id],
            ]);

            // 3. Crear la Actividad
            $activity = Activity::create([
                'contact_id'            => $this->contactId,
                'type'                  => 'correo',
                'title'                 => ($parentActivity ? 'Re: ' : 'Correo Enviado: ') . $this->emailData['subject'],
                'description'           => $this->stripHtml($this->emailData['body']),
                'html_description'      => $this->emailData['body'],
                'has_inline_images'     => false,
                'email_to'              => $this->emailData['to'],
                'email_cc'              => $this->emailData['cc'] ?? [],
                'email_bcc'             => $this->emailData['bcc'] ?? [],
                'external_message_id'   => $messageId,
                'in_reply_to'           => $parentActivity ? $this->normalizeMessageId($parentActivity->external_message_id, true) : null,
                'references'            => $parentActivity ? $this->getReferencesHeader($parentActivity) : null,
                'parent_activity_id'    => $parentActivity ? $parentActivity->id : null,
                'email_log_id'          => $emailLog->id,
                'created_by'            => $user->id,
            ]);

            // 4. Mover adjuntos a su ubicación permanente y crear registros
            $this->processActivityAttachments($activity);

            DB::commit();

        } catch (Throwable $e) {
            DB::rollBack();
            // Esto hará que el job falle y pueda ser reintentado por la cola
            throw $e;
        } finally {
            // 5. Limpiar los archivos temporales, incluso si el job falla
            $this->cleanupTemporaryFiles();
        }
    }

    /**
     * Maneja el fallo del job, asegurando la limpieza de archivos.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('Fallo el job de envío de correo', [
            'user_id' => $this->userId,
            'contact_id' => $this->contactId,
            'error' => $exception->getMessage()
        ]);
        $this->cleanupTemporaryFiles();
    }

    // --- MÉTODOS DE AYUDA (Movidos desde el controlador) ---

    protected function buildTransport(UserEmailAccount $account): EsmtpTransport
    {
        $encryption = ($account->smtp_encryption === 'none' || empty($account->smtp_encryption)) ? null : $account->smtp_encryption;
        $transport = new EsmtpTransport($account->smtp_host, (int) $account->smtp_port, $encryption);
        if (!empty($account->smtp_username)) {
            $transport->setUsername($account->smtp_username);
        }
        if (!empty($account->password)) {
            $transport->setPassword($account->password);
        }
        return $transport;
    }

    private function addReplyHeaders(SymfonyEmail $message, Activity $parentActivity): void
    {
        $parentMessageIdRaw = $parentActivity->external_message_id ?? null;
        $parentMessageId = $this->normalizeMessageId($parentMessageIdRaw);

        if ($parentMessageId) {
            $message->getHeaders()->addIdHeader('In-Reply-To', $parentMessageId);
            $referencesHeader = $this->getReferencesHeader($parentActivity);
            if ($referencesHeader) {
                $message->getHeaders()->addTextHeader('References', $referencesHeader);
            }
        }
    }
    
    private function getReferencesHeader(Activity $parentActivity): ?string
    {
        $parentMessageIdRaw = $parentActivity->external_message_id ?? null;
        $parentMessageId = $this->normalizeMessageId($parentMessageIdRaw);
        if (!$parentMessageId) return null;

        $existingRefs = $parentActivity->references ?: '';
        $refsArray = array_filter(preg_split('/\s+/', trim($existingRefs)) ?: []);
        $refsNorm = array_map(fn($id) => $this->normalizeMessageId($id, true), $refsArray);
        
        if (!in_array($this->normalizeMessageId($parentMessageId, true), $refsNorm)) {
            $refsNorm[] = $this->normalizeMessageId($parentMessageId, true);
        }
        
        return implode(' ', array_filter($refsNorm));
    }

    private function normalizeMessageId(?string $id, bool $withBrackets = false): ?string
    {
        if (!$id) return null;
        $id = trim(preg_replace('/^<|>$/', '', trim($id)));
        return $id ? ($withBrackets ? "<{$id}>" : $id) : null;
    }

    private function stripHtml(?string $html): string
    {
        if (!$html) return '';
        return trim(preg_replace('/\s+/', ' ', strip_tags($html)));
    }
    
    private function processActivityAttachments(Activity $activity): void
    {
        if (empty($this->emailData['attachments'])) {
            return;
        }

        $disk = 'public';
        foreach ($this->emailData['attachments'] as $fileInfo) {
            $permanentPath = "activities/{$activity->id}/attachments/{$fileInfo['original_name']}";
            
            // Mover el archivo de la ubicación temporal a la permanente
            if (Storage::exists($fileInfo['path'])) {
                Storage::disk($disk)->move($fileInfo['path'], $permanentPath);
            }
            
            ActivityAttachment::create([
                'activity_id' => $activity->id,
                'filename'    => $fileInfo['original_name'],
                'disk'        => $disk,
                'path'        => $permanentPath,
                'mime'        => $fileInfo['mime_type'],
                'size'        => $fileInfo['size'],
                'is_inline'   => false,
            ]);
        }
    }

    private function cleanupTemporaryFiles(): void
    {
        foreach ($this->emailData['attachments'] ?? [] as $attachment) {
            if (Storage::exists($attachment['path'])) {
                Storage::delete($attachment['path']);
            }
        }
    }
}