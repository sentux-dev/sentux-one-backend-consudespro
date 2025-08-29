<?php

namespace App\Http\Controllers\Api\Crm;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

use App\Models\UserEmailAccount;
use App\Models\Marketing\EmailLog;
use App\Models\Crm\Activity;
use App\Models\Crm\ActivityAttachment;

use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Email as SymfonyEmail;
use Symfony\Component\Mime\Address;
use Throwable;

class EmailController extends Controller
{
    public function send(Request $request)
    {
        $validated = $request->validate([
            'from_account_id' => 'required|integer',
            'contact_id'      => 'required|integer|exists:crm_contacts,id',
            'to'              => 'required|array|min:1',
            'to.*'            => 'required|email',
            'cc'              => 'nullable|array',
            'cc.*'            => 'sometimes|required|email',
            'bcc'             => 'nullable|array',
            'bcc.*'           => 'sometimes|required|email',
            'subject'         => 'required|string|max:255',
            'body'            => 'required|string',
            'from_name'       => 'nullable|string|max:255',
            'reply_to'        => 'nullable|email',
            'text_body'       => 'nullable|string',
            'attachments'     => 'nullable|array|max:5',
            'attachments.*' => 'file|max:20480|mimes:
                doc,docx,dot,dotx,docm,dotm,rtf,
                xls,xlsx,xlt,xltx,xlsm,xltm,csv,
                ppt,pptx,pot,potx,pps,ppsx,pptm,potm,ppsm,
                odt,ott,ods,ots,odp,otp,odg,otg,odf,
                pdf,txt,zip,rar'

        ]);

        $user = $request->user();
        $account = UserEmailAccount::where('user_id', $user->id)
            ->where('id', $validated['from_account_id'])
            ->firstOrFail();

        // 1) Construir transporte/mailer por cuenta
        $transport = $this->buildTransport($account);
        $mailer = new Mailer($transport);

        // 2) Construir mensaje
        $fromEmail = $account->email_address ?? $account->smtp_username ?? null;
        if (!$fromEmail) {
            return response()->json(['message' => 'La cuenta seleccionada no tiene correo de salida válido.'], 422);
        }
        $fromName = $validated['from_name'] ?? ($account->display_name ?? null);
        $from     = $fromName ? new Address($fromEmail, $fromName) : new Address($fromEmail);

        $message = (new SymfonyEmail())
            ->from($from)
            ->subject($validated['subject'])
            ->html($validated['body']);

        if (!empty($validated['text_body'])) {
            $message->text($validated['text_body']);
        }
        if (!empty($validated['reply_to'])) {
            $message->replyTo(new Address($validated['reply_to']));
        }

        foreach ($validated['to'] as $to)   { $message->addTo(new Address($to)); }
        foreach ($validated['cc'] ?? [] as $cc)   { $message->addCc(new Address($cc)); }
        foreach ($validated['bcc'] ?? [] as $bcc) { $message->addBcc(new Address($bcc)); }

        // Adjuntar archivos (para el correo)
        $uploadedFiles = [];
        if ($request->hasFile('attachments')) {
            $uploadedFiles = $request->file('attachments');
            foreach ($uploadedFiles as $file) {
                $message->attachFromPath(
                    $file->getRealPath(),
                    $file->getClientOriginalName()
                );
            }
        }

        // Vamos en transacción: si algo falla, no quedan registros huérfanos.
        DB::beginTransaction();

        try {
            // 3) Enviar
            $mailer->send($message);

            // Puede que el message-id esté disponible después de send (según transporte)
            $messageId = null;
            try {
                $messageId = $message->getHeaders()->get('Message-ID')?->getBody();
            } catch (\Throwable $e) {
                // si no está, lo dejamos null
            }

            // 4) EmailLog (si tu modelo existe)
            $emailLogId = null;
            if (class_exists(EmailLog::class)) {
                $log = EmailLog::create([
                    'user_id'     => $user->id,
                    'contact_id'  => $validated['contact_id'],
                    'to'          => $validated['to'],
                    'cc'          => $validated['cc'] ?? [],
                    'bcc'         => $validated['bcc'] ?? [],
                    'subject'     => $validated['subject'],
                    'body'        => $validated['body'],
                    'status'      => 'enviado',
                    'provider'    => 'smtp',
                    'message_id'  => $messageId,
                    'meta'        => [
                        'from'      => $fromEmail,
                        'from_name' => $fromName,
                        'account'   => $account->id,
                    ],
                ]);
                $emailLogId = $log->id;
            }

            // 5) Activity (llenar TODOS los campos de email para UI/threads/reply-all)
            $activity = Activity::create([
                'contact_id'            => $validated['contact_id'],
                'type'                  => 'correo',
                'title'                 => 'Correo Enviado: ' . $validated['subject'],
                'description'           => $this->stripHtml($validated['body']), // snippet/legacy
                'html_description'      => $validated['body'],                   // UI principal
                'has_inline_images'     => false,                                // si luego embebes CID, actualiza
                'original_recipients'   => ['to' => $validated['to'], 'cc' => $validated['cc'] ?? []],
                'sender_name'            => $fromName,
                'sender_email'           => $fromEmail,
                'email_to'              => $validated['to'],
                'email_cc'              => $validated['cc'] ?? [],
                'email_bcc'             => $validated['bcc'] ?? [],
                'external_message_id'   => $messageId,
                // 'in_reply_to'         => null,              // llena si respondes sobre un hilo existente
                // 'references'          => null,
                // 'thread_root_message_id' => null,
                'email_log_id'          => $emailLogId,
                'created_by'            => $user->id,
            ]);

            // 6) Guardar físicamente adjuntos de la Activity + filas en ActivityAttachment
            //    (esto es independiente a adjuntar en el correo)
            if (!empty($uploadedFiles)) {
                $disk = 'public'; // ajusta si usas otro disk
                foreach ($uploadedFiles as $file) {
                    $original = $file->getClientOriginalName();

                    // path: activities/{activity_id}/attachments/{original}
                    $basePath = "activities/{$activity->id}/attachments";
                    // Evita colisiones añadiendo timestamp si ya existe
                    $target   = $basePath . '/' . $original;
                    if (Storage::disk($disk)->exists($target)) {
                        $nameOnly = pathinfo($original, PATHINFO_FILENAME);
                        $ext      = pathinfo($original, PATHINFO_EXTENSION);
                        $target   = $basePath . '/' . $nameOnly . '-' . now()->format('YmdHis') . ($ext ? ".{$ext}" : '');
                    }

                    // mover al disk
                    $storedPath = $file->storeAs($basePath, basename($target), $disk);

                    // Crear registro de adjunto
                    ActivityAttachment::create([
                        'activity_id' => $activity->id,
                        'filename'    => basename($target),
                        'disk'        => $disk,
                        'path'        => $storedPath, // relativo al disk
                        'mime'        => $file->getClientMimeType(),
                        'size'        => $file->getSize(),
                        'is_inline'   => false,
                        'cid'         => null,
                    ]);
                }
            }

            DB::commit();

            return response()->json(['message' => 'Correo enviado y actividad registrada con adjuntos.']);

        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('Error al enviar correo / registrar actividad', [
                'error'      => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
                'account_id' => $account->id,
                'user_id'    => $user->id,
            ]);

            return response()->json([
                'message' => 'Error al enviar/registrar.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    protected function buildTransport(UserEmailAccount $account): EsmtpTransport
    {
        $encryption = ($account->smtp_encryption === 'none' || empty($account->smtp_encryption))
            ? null
            : $account->smtp_encryption;

        $transport = new EsmtpTransport(
            $account->smtp_host,
            (int) $account->smtp_port,
            $encryption
        );

        if (!empty($account->smtp_username)) {
            $transport->setUsername($account->smtp_username);
        }
        if (!empty($account->password)) {
            $transport->setPassword($account->password);
        }

        return $transport;
    }

    private function stripHtml(?string $html): string
    {
        if (!$html) return '';
        return trim(preg_replace('/\s+/', ' ', strip_tags($html)));
    }

    public function reply(Request $request)
    {
        $validated = $request->validate([
            'from_account_id'     => 'required|integer',
            'contact_id'          => 'required|integer|exists:crm_contacts,id',
            'parent_activity_id'  => 'required|integer|exists:crm_activities,id',
            'reply_mode'          => 'required|string|in:reply,reply_all',
            'subject'             => 'required|string|max:255',
            'body'                => 'required|string',
            'text_body'           => 'nullable|string',

            // Adjuntos (Office, OpenOffice, Google export, comunes)
            'attachments'         => 'nullable|array|max:5',
            'attachments.*'       => 'file|max:20480|mimes:doc,docx,dot,dotx,docm,dotm,rtf,xls,xlsx,xlt,xltx,xlsm,xltm,csv,ppt,pptx,pot,potx,pps,ppsx,pptm,potm,ppsm,odt,ott,ods,ots,odp,otp,odg,otg,odf,pdf,txt,zip,rar',

            // Overrides opcionales (el servidor recalculará igualmente)
            'to'         => 'nullable|array',
            'to.*'       => 'email',
            'cc'         => 'nullable|array',
            'cc.*'       => 'email',
            'bcc'        => 'nullable|array',
            'bcc.*'      => 'email',
        ]);

        $user = $request->user();

        // Cuenta del usuario
        $account = \App\Models\UserEmailAccount::where('user_id', $user->id)
            ->where('id', $validated['from_account_id'])
            ->firstOrFail();

        // Actividad padre (correo original)
        /** @var \App\Models\Crm\Activity $parent */
        $parent = \App\Models\Crm\Activity::with('emailLog')->findOrFail($validated['parent_activity_id']);

        if ((int)$parent->contact_id !== (int)$validated['contact_id']) {
            return response()->json(['message' => 'El correo seleccionado no pertenece a este contacto.'], 422);
        }

        // Construcción de destinatarios
        $selfEmails = array_filter([$account->email_address, $account->smtp_username], fn($e) => !empty($e));

        $origFrom = $parent->emailLog->from_email
            ?? $parent->emailLog->from
            ?? $parent->sender_email
            ?? null;
        $origFromArr = $origFrom ? [$origFrom] : [];

        $origTo = $parent->email_to ?? ($parent->emailLog->to ?? []);
        $origCc = $parent->email_cc ?? ($parent->emailLog->cc ?? []);

        $to  = [];
        $cc  = [];
        $bcc = [];

        if (!empty($validated['to'])) {
            $to  = $validated['to'];
            $cc  = $validated['cc']  ?? [];
            $bcc = $validated['bcc'] ?? [];
        } else {
            if ($validated['reply_mode'] === 'reply') {
                $to = $origFromArr;
            } else { // reply_all
                $to = array_unique(array_merge($origFromArr, is_array($origTo) ? $origTo : []));
                $cc = is_array($origCc) ? $origCc : [];
            }
            // Excluir direcciones propias
            $lowerSelf = array_map('strtolower', $selfEmails);
            $to = array_values(array_filter($to, fn($e) => $e && !in_array(strtolower($e), $lowerSelf)));
            $cc = array_values(array_filter($cc, fn($e) => $e && !in_array(strtolower($e), $lowerSelf)));
        }

        if (empty($to)) {
            return response()->json(['message' => 'No se pudo determinar destinatarios para la respuesta.'], 422);
        }

        // Transport/Mailer
        $transport = $this->buildTransport($account);
        $mailer = new \Symfony\Component\Mailer\Mailer($transport);

        // From
        $fromEmail = $account->email_address ?? $account->smtp_username ?? null;
        if (!$fromEmail) {
            return response()->json(['message' => 'La cuenta seleccionada no tiene correo de salida válido.'], 422);
        }
        $fromName = $account->display_name ?? null;
        $from = $fromName
            ? new \Symfony\Component\Mime\Address($fromEmail, $fromName)
            : new \Symfony\Component\Mime\Address($fromEmail);

        // Mensaje
        $message = (new \Symfony\Component\Mime\Email())
            ->from($from)
            ->subject($validated['subject'])
            ->html($validated['body']);

        if (!empty($validated['text_body'])) {
            $message->text($validated['text_body']);
        }

        foreach ($to as $addr)  { $message->addTo(new \Symfony\Component\Mime\Address($addr)); }
        foreach ($cc as $addr)  { $message->addCc(new \Symfony\Component\Mime\Address($addr)); }
        foreach ($bcc as $addr) { $message->addBcc(new \Symfony\Component\Mime\Address($addr)); }

        // ====== HILO (solo si hay Message-ID del padre) ======
        $parentMessageIdRaw =
            $parent->external_message_id
            ?? ($parent->emailLog->message_id ?? null);

        $parentMessageId = $this->normalizeMessageId($parentMessageIdRaw);

        if ($parentMessageId) {
            // In-Reply-To
            $message->getHeaders()->addIdHeader('In-Reply-To', $parentMessageId);

            // References: <id1> <id2> ... <parent>
            $existingRefs = $parent->references ?: '';
            $refsArray = array_values(array_filter(preg_split('/\s+/', trim($existingRefs)) ?: []));
            $refsNorm = array_values(array_filter(array_map([$this, 'normalizeMessageId'], $refsArray)));

            if (!in_array($parentMessageId, $refsNorm, true)) {
                $refsNorm[] = $parentMessageId;
            }

            $refsHeader = implode(' ', array_map([$this, 'wrapId'], $refsNorm));
            if ($refsHeader) {
                $message->getHeaders()->addTextHeader('References', $refsHeader);
            }
        }
        // =====================================================

        // Adjuntos (al correo)
        $uploadedFiles = [];
        if ($request->hasFile('attachments')) {
            $uploadedFiles = $request->file('attachments');
            foreach ($uploadedFiles as $file) {
                $message->attachFromPath(
                    $file->getRealPath(),
                    $file->getClientOriginalName()
                );
            }
        }

        \DB::beginTransaction();
        try {
            // Enviar
            $mailer->send($message);

            // Message-ID nuevo (puede venir sin < >)
            $messageId = null;
            try {
                $messageId = $message->getHeaders()->get('Message-ID')?->getBody();
            } catch (\Throwable $e) {}

            // EmailLog (si existe tu modelo)
            $emailLogId = null;
            if (class_exists(\App\Models\Marketing\EmailLog::class)) {
                $log = \App\Models\Marketing\EmailLog::create([
                    'user_id'     => $user->id,
                    'contact_id'  => $validated['contact_id'],
                    'to'          => $to,
                    'cc'          => $cc,
                    'bcc'         => $bcc,
                    'subject'     => $validated['subject'],
                    'body'        => $validated['body'],
                    'status'      => 'enviado',
                    'provider'    => 'smtp',
                    'message_id'  => $messageId,
                    'meta'        => [
                        'from'       => $fromEmail,
                        'from_name'  => $fromName,
                        'account'    => $account->id,
                        'reply_mode' => $validated['reply_mode'],
                    ],
                ]);
                $emailLogId = $log->id;
            }

            // Thread IDs condicionales (solo si tuvimos parentMessageId)
            $inReplyToHeader   = $parentMessageId ? $this->wrapId($parentMessageId) : null;
            $referencesHeader  = $parentMessageId
                ? ($refsHeader ?? null)
                : null;
            $threadRootMessage = $parentMessageId
                ? ($parent->thread_root_message_id ?: $parentMessageIdRaw)
                : null;

            // Crear Activity hija
            $activity = \App\Models\Crm\Activity::create([
                'contact_id'            => $validated['contact_id'],
                'type'                  => 'correo',
                'title'                 => 'Re: ' . ($parent->title ?? $validated['subject']),
                'description'           => $this->stripHtml($validated['body']),
                'html_description'      => $validated['body'],
                'has_inline_images'     => false,
                'original_recipients'   => ['to' => $to, 'cc' => $cc],
                'email_to'              => $to,
                'email_cc'              => $cc,
                'email_bcc'             => $bcc,
                'external_message_id'   => $messageId,
                'in_reply_to'           => $inReplyToHeader,          // null si no hay hilo
                'references'            => $referencesHeader,         // null si no hay hilo
                'thread_root_message_id'=> $threadRootMessage,        // null si no hay hilo
                'parent_activity_id'    => $parent->id,               // mantiene relación padre-hijo en CRM
                'email_log_id'          => $emailLogId,
                'created_by'            => $user->id,
                // opcional para UI de recibidos:
                'sender_email'          => $fromEmail,
                'sender_name'           => $fromName,
            ]);

            // Guardar adjuntos físicamente y en tabla
            if (!empty($uploadedFiles)) {
                $disk = 'public';
                foreach ($uploadedFiles as $file) {
                    $original = $file->getClientOriginalName();
                    $basePath = "activities/{$activity->id}/attachments";
                    $target   = $basePath . '/' . $original;
                    if (\Storage::disk($disk)->exists($target)) {
                        $nameOnly = pathinfo($original, PATHINFO_FILENAME);
                        $ext      = pathinfo($original, PATHINFO_EXTENSION);
                        $target   = $basePath . '/' . $nameOnly . '-' . now()->format('YmdHis') . ($ext ? ".{$ext}" : '');
                    }
                    $storedPath = $file->storeAs($basePath, basename($target), $disk);

                    \App\Models\Crm\ActivityAttachment::create([
                        'activity_id' => $activity->id,
                        'filename'    => basename($target),
                        'disk'        => $disk,
                        'path'        => $storedPath,
                        'mime'        => $file->getClientMimeType(),
                        'size'        => $file->getSize(),
                        'is_inline'   => false,
                        'cid'         => null,
                    ]);
                }
            }

            \DB::commit();
            return response()->json([
            'message'  => 'Correo enviado con éxito.',
            ]);


        } catch (\Throwable $e) {
            \DB::rollBack();
            \Log::error('Error reply()', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Error al responder el correo.', 'error' => $e->getMessage()], 500);
        }
    }

    /** Normaliza un Message-ID quitando < > y espacios. */
    private function normalizeMessageId(?string $id): ?string
    {
        if (!$id) return null;
        $id = trim($id);
        $id = preg_replace('/^<|>$/', '', $id);
        return $id ?: null;
    }

    /** Envuelve un id simple como <id> para usar en References, etc. */
    private function wrapId(string $id): string
    {
        $id = $this->normalizeMessageId($id);
        return $id ? "<{$id}>" : '';
    }




}
