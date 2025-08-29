<?php

namespace App\Services;

use App\Models\UserEmailAccount;
use App\Models\Crm\Contact;
use App\Models\Crm\Activity;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Client as ImapClient;
use Webklex\PHPIMAP\Folder;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use App\Models\Crm\ActivityAttachment;
use App\Notifications\NewEmailReceivedNotification;
use App\Models\User;

class MailSyncService
{
    /**
     * Sincroniza una cuenta de correo específica.
     *
     * @param UserEmailAccount $account
     * @return array{status:'success'|'fail', synced:int, message:string}
     */
    public function syncAccount(UserEmailAccount $account): array
    {
        try {
            $client = $this->connectToAccount($account);

            // INBOX robusto
            $folder = $this->getInboxFolder($client);

            // Query incremental por fecha (buffer 5 min) + límite por lote
            $query = $folder->query()->setFetchOrder('asc');
            $batchLimit = 200;

            $since = $account->last_sync_at
                ? Carbon::parse($account->last_sync_at)->subMinutes(5)
                : now()->subDays(7);

            $messages = $query->since($since)->limit($batchLimit)->get();

            $syncedCount   = 0;
            $maxMessageDate = $account->last_sync_at ? Carbon::parse($account->last_sync_at) : $since;

            foreach ($messages as $message) {
                $msgDate = $this->safeMessageDate($message) ?? now();
                if ($msgDate->gt($maxMessageDate)) {
                    $maxMessageDate = $msgDate;
                }

                $this->processMessage($message, $account);
                $syncedCount++;
            }

            // Actualiza marcas de sync
            $account->update([
                'last_sync_at'       => max($maxMessageDate, now()),
                'sync_error_message' => null,
            ]);

            $client->disconnect();

            return ['status' => 'success', 'synced' => $syncedCount, 'message' => 'Sincronización completada.'];

        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
            Log::error("Fallo de sincronización para {$account->email_address}: {$errorMessage}");
            $account->update(['sync_error_message' => $errorMessage]);
            return ['status' => 'fail', 'synced' => 0, 'message' => $errorMessage];
        }
    }

    /**
     * Procesa un mensaje de correo individual (idempotente por Message-ID).
     */
    private function processMessage($message, UserEmailAccount $account): void
    {
        try {
            $messageId = (string) $message->getMessageId() ?: null;

            if ($messageId && \App\Models\Crm\Activity::where('external_message_id', $messageId)->exists()) {
                Log::info('Sync: actividad ya existe para Message-ID', ['message_id' => $messageId]);
                return;
            }

            // Remitente robusto (usa tu helper si ya lo agregaste)
            [$fromEmail, $fromSource] = method_exists($this, 'extractBestFromEmail')
                ? $this->extractBestFromEmail($message)
                : [null, 'NONE'];

            if (!$fromEmail) {
                Log::warning('Sync: email remitente vacío; se omite', ['message_id' => $messageId]);
                return;
            }

            $contact = \App\Models\Crm\Contact::whereRaw('LOWER(email) = ?', [$fromEmail])
                ->where('owner_id', $account->user_id)
                ->first();

            if (!$contact) {
                Log::info('Sync: no se encontró contacto para remitente', [
                    'from' => $fromEmail, 'user_id' => $account->user_id, 'message_id' => $messageId
                ]);
                return;
            }

            $subject    = $message->getSubject() ?: '(sin asunto)';
            $textBody   = $message->getTextBody();
            $rawHtml    = $message->getHtmlBody();

            // Reescritura de imágenes inline (cid) -> URL pública
            $htmlRewritten = null;
            $hasInlineImages = false;

            // Log html
            Log::debug('Sync: HTML original', ['html' => $rawHtml]);

            if (!empty($rawHtml)) {
                $htmlRewritten = $this->rewriteInlineImages($message, $account, $rawHtml);
                $hasInlineImages = $htmlRewritten['has_inline'];
                Log::debug('Sync: HTML reescrito', ['html' => $htmlRewritten['html']]);
                $rawHtml = $htmlRewritten['html']; // HTML final con src reescritos
            }

            // Fallback texto si no hay text/plain
            if (empty($textBody)) {
                $textBody = $rawHtml ? strip_tags($rawHtml) : '';
            }

            $activity = \App\Models\Crm\Activity::create([
                'contact_id'          => $contact->id,
                'external_message_id' => $messageId,
                'type'                => 'correo',
                'title'               => 'Correo Recibido: ' . $subject,
                'description'         => $textBody,
                'html_description'    => $rawHtml ?: null,
                'has_inline_images'   => $hasInlineImages,
                'created_by'          => null,
            ]);

            $this->storeNonInlineAttachments($message, $account, $activity);

            $userToNotify = $account->user;
            if ($userToNotify) {
                $userToNotify->notify(new NewEmailReceivedNotification($activity));
            }

            Log::info('Sync: actividad creada', [
                'activity_id' => $activity->id ?? null,
                'contact_id'  => $contact->id,
                'from'        => $fromEmail,
                'message_id'  => $messageId
            ]);

        } catch (\Throwable $e) {
            Log::error('Sync: error creando actividad', [
                'error'      => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
                'message_id' => (string) $message->getMessageId(),
            ]);
        }
    }


    /**
     * Intenta extraer el mejor remitente posible:
     * 1) From
     * 2) Sender
     * 3) Reply-To
     * 4) Return-Path
     * 5) Header "From" crudo (regex)
     *
     * @return array{0:?string,1:string} [emailNormalizado|null, fuente]
     */
    private function extractBestFromEmail($message): array
    {
        // Helpers que devuelven AddressCollection (o null)
        $tryCollections = [
            'from'      => fn() => $message->getFrom(),
            'sender'    => fn() => $message->getSender(),
            'reply-to'  => fn() => $message->getReplyTo(),
        ];

        foreach ($tryCollections as $source => $getter) {
            try {
                $col = $getter();
                if ($col && method_exists($col, 'count') && $col->count() > 0) {
                    // En Webklex, AddressCollection soporta first()
                    $addr = method_exists($col, 'first') ? $col->first() : ($col[0] ?? null);
                    $mail = $addr->mail ?? null;
                    $email = $this->normalizeEmail($mail);
                    if ($email) return [$email, strtoupper($source)];
                }
            } catch (\Throwable $e) {
                // ignorar y seguir con otros headers
            }
        }

        // Return-Path (string simple o Attribute)
        try {
            $rp = (string) $message->getReturnPath();
            $email = $this->normalizeEmail($rp);
            if ($email) return [$email, 'RETURN-PATH'];
        } catch (\Throwable $e) {}

        // Header "From" crudo (string) y extraer con regex
        try {
            $rawFrom = (string) $message->getHeader('From');
            $email = $this->normalizeEmail($rawFrom);
            if ($email) return [$email, 'FROM-HEADER-RAW'];
        } catch (\Throwable $e) {}

        return [null, 'NONE'];
    }

    /**
     * Normaliza y valida un email dentro de una cadena: toma el primero que luzca válido.
     */
    private function normalizeEmail(?string $raw): ?string
    {
        if (!$raw) return null;
        // Extrae la primera dirección tipo local@dominio
        if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $raw, $m)) {
            return strtolower($m[0]);
        }
        return null;
    }

    /**
     * Establece la conexión con la cuenta IMAP.
     */
    private function connectToAccount(UserEmailAccount $account): ImapClient
    {
        $config = [
            'host'          => $account->imap_host,
            'port'          => (int) $account->imap_port,
            'encryption'    => $account->imap_encryption ?: null,
            'validate_cert' => true,
            'username'      => $account->imap_username,
            'password'      => $account->password, // desencriptado por el modelo
            'protocol'      => 'imap',
            'timeout'       => 15,
        ];

        $clientManager = new ClientManager();
        $client = $clientManager->make($config);
        $client->connect();

        return $client;
    }

    /**
     * Devuelve la carpeta INBOX de forma robusta.
     */
    private function getInboxFolder(ImapClient $client): Folder
    {
        // Intento directo
        try {
            return $client->getFolderByName('INBOX');
        } catch (\Throwable $e) {
            // Buscar case-insensitive
            $folders = $client->getFolders();
            foreach ($folders as $f) {
                if (strcasecmp($f->name, 'INBOX') === 0 || strcasecmp($f->fullname, 'INBOX') === 0) {
                    return $f;
                }
            }
            foreach ($folders as $f) {
                if (stripos($f->name, 'inbox') !== false || stripos($f->fullname, 'inbox') !== false) {
                    return $f;
                }
            }
            throw new Exception('No se encontró la carpeta INBOX.');
        }
    }

    /**
     * Obtiene la fecha del mensaje como Carbon, tolerante a servidores raros.
     */
    private function safeMessageDate($message): ?Carbon
    {
        try {
            $date = $message->getDate();
            if ($date instanceof Carbon) return $date;
            if ($date) return Carbon::parse((string) $date);
        } catch (\Throwable $e) {
            // ignorar
        }
        return null;
    }

    private function rewriteInlineImages($message, UserEmailAccount $account, string $html): array
    {
        $map = []; // content-id => url pública
        $hasInline = false;

        try {
            $attachments = $message->getAttachments(); // \Webklex\PHPIMAP\Support\AttachmentCollection
            if ($attachments && $attachments->count() > 0) {
                foreach ($attachments as $att) {
                    // Muchos proveedores marcan inline via content-id o disposition
                    $contentId = $att->getContentID() ?: $att->content_id ?? null;
                    $disposition = strtolower((string)($att->disposition ?? ''));
                    $mime = strtolower((string)($att->getMimeType() ?? ''));

                    $isImage = str_starts_with($mime, 'image/');
                    $isInline = $contentId || $disposition === 'inline';

                    if ($isImage && $isInline) {
                        // Ruta de guardado: public/emails/{user}/{account}/{uid}/filename
                        $uid = (int) ($message->getUid() ?? 0);
                        $basePath = "emails/{$account->user_id}/{$account->id}/".($uid ?: 'no-uid');

                        $rawName  = $att->getName() ?: ($contentId ? trim($contentId, '<>') : uniqid('img_'));
                        $decoded  = $this->decodeMimeHeader($rawName);
                        $clean    = $this->sanitizeFilename($decoded);
                        $clean    = $this->ensureExtension($clean, $mime);
                        $filename = $this->uniqueFilename($basePath, $clean);

                        $path = $basePath.'/'.$filename;
                        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
                        $disk = Storage::disk('public');

                        $disk->put($path, $att->getContent());
                        $publicUrl = $disk->url($path);
                        if ($contentId) {
                            $cidKey = trim($contentId, '<>'); // los cids vienen con <>
                            $map[$cidKey] = $publicUrl;
                        }

                        $hasInline = true;
                    }
                }
            }

            // Reemplazar src="cid:xxxx" por la URL pública
            if (!empty($map)) {
                // Reemplazo seguro de cids encontrados
                $html = preg_replace_callback('/src=["\']cid:([^"\']+)["\']/i', function ($m) use ($map) {
                    $cid = $m[1];
                    if (isset($map[$cid])) {
                        return 'src="'.$map[$cid].'" referrerpolicy="no-referrer"';
                    }
                    return $m[0];
                }, $html);
            }

        } catch (\Throwable $e) {
            Log::warning('Sync: fallo procesando imágenes inline', ['error' => $e->getMessage()]);
        }

        return ['html' => $html, 'has_inline' => $hasInline];
    }

    private function storeNonInlineAttachments($message, \App\Models\UserEmailAccount $account, \App\Models\Crm\Activity $activity): void
    {
        try {
            $attachments = $message->getAttachments();
            if (!$attachments || $attachments->count() === 0) return;

            $uid = (int) ($message->getUid() ?? 0);
            $basePath = "emails/{$account->user_id}/{$account->id}/".($uid ?: 'no-uid')."/attachments";

            foreach ($attachments as $att) {
                $contentId   = $att->getContentID() ?: $att->content_id ?? null;
                $disposition = strtolower((string) ($att->disposition ?? ''));
                $mime        = strtolower((string) ($att->getMimeType() ?? 'application/octet-stream'));

                // Nombre original (puede venir RFC 2047)
                $rawName = $att->getName() ?: ($contentId ? trim($contentId, '<>') : uniqid('file_'));

                // ✅ Decodificar + sanitizar + asegurar extensión + evitar colisión
                $decoded  = $this->decodeMimeHeader($rawName);
                $clean    = $this->sanitizeFilename($decoded);
                $clean    = $this->ensureExtension($clean, $mime);
                $name     = $this->uniqueFilename($basePath, $clean);

                // ya tratamos inline image en rewriteInlineImages; aquí guardamos los demás
                $isImage  = str_starts_with($mime, 'image/');
                $isInline = $contentId || $disposition === 'inline';
                if ($isImage && $isInline) {
                    // Fue manejado al reescribir el HTML (cid -> url), no duplicar
                    continue;
                }

                $path = $basePath.'/'.$name;

                // Guardar binario
                $stream = $att->getContent();
                Storage::disk('public')->put($path, $stream);

                $activity->attachments()->create([
                    'filename'   => $name,
                    'mime_type'  => $mime,
                    'size'       => is_string($stream) ? strlen($stream) : null,
                    'path'       => $path,
                    'is_inline'  => (bool) $isInline,
                    'content_id' => $contentId ? trim($contentId, '<>') : null,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Sync: fallo guardando adjuntos', ['error' => $e->getMessage()]);
        }
    }


    /**
     * Decodifica un header estilo RFC 2047 (=?utf-8?Q?...?= o =?utf-8?B?...?=).
     * Soporta múltiples “encoded-words” en la misma cadena.
     */
    private function decodeMimeHeader(string $value): string {
        // Intenta con funciones nativas si existen
        if (function_exists('iconv_mime_decode')) {
            $dec = @iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
            if ($dec !== false && $dec !== null) return $dec;
        }
        if (function_exists('mb_decode_mimeheader')) {
            $dec = @mb_decode_mimeheader($value);
            if ($dec !== false && $dec !== null) return $dec;
        }

        // Fallback manual para Q/B
        return preg_replace_callback('/=\?([^?]+)\?([BQbq])\?([^?]+)\?=/u', function($m) {
            $charset = strtoupper($m[1] ?? 'UTF-8');
            $enc     = strtoupper($m[2] ?? 'Q');
            $text    = $m[3] ?? '';

            if ($enc === 'B') {
                $bin = base64_decode($text, true);
                if ($bin === false) return $text;
                // convertir al utf-8 si es posible
                return @mb_convert_encoding($bin, 'UTF-8', $charset) ?: $bin;
            } else {
                // Q-encoding: _ => espacio, =xx => byte
                $text = str_replace('_', ' ', $text);
                $bin  = quoted_printable_decode($text);
                return @mb_convert_encoding($bin, 'UTF-8', $charset) ?: $bin;
            }
        }, $value) ?? $value;
    }

    /**
     * Limpia el nombre de archivo de caracteres problemáticos y limita su longitud.
     */
    private function sanitizeFilename(string $name): string {
        $name = trim($name);
        // quitar rutas/dirs
        $name = preg_replace('#[\\\\/]+#', '-', $name);
        // quitar caracteres peligrosos/no válidos en la mayoría de FS/URLs
        $name = preg_replace('/[^\pL\pN\.\-\_\s]/u', '', $name);
        // colapsar espacios
        $name = preg_replace('/\s+/', ' ', $name);
        $name = trim($name, " .\t\n\r\0\x0B");
        if ($name === '' ) $name = 'archivo';

        // limitar longitud total
        if (mb_strlen($name) > 160) {
            $ext = pathinfo($name, PATHINFO_EXTENSION);
            $base= mb_substr(pathinfo($name, PATHINFO_FILENAME), 0, 140);
            $name = $ext ? ($base.'.'.$ext) : $base;
        }
        return $name;
    }

    /**
     * Asegura que el nombre tenga extensión. Si no la tiene, infiere desde el mime.
     */
    private function ensureExtension(string $name, ?string $mime): string {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($ext) return $name;

        $map = [
            'image/png' => 'png', 'image/jpeg' => 'jpg', 'image/jpg' => 'jpg',
            'image/gif' => 'gif', 'image/webp' => 'webp', 'image/svg+xml' => 'svg',
            'application/pdf' => 'pdf', 'text/plain' => 'txt',
        ];
        $guess = $map[strtolower((string)$mime)] ?? 'bin';
        return $name.'.'.$guess;
    }

    /**
     * Genera un nombre único dentro de un path de Storage::disk('public').
     */
    private function uniqueFilename(string $basePath, string $name): string {
        $ext  = pathinfo($name, PATHINFO_EXTENSION);
        $base = pathinfo($name, PATHINFO_FILENAME);

        $candidate = $name;
        $i = 1;
        while (Storage::disk('public')->exists($basePath.'/'.$candidate)) {
            $candidate = $base.'-'.$i.($ext ? '.'.$ext : '');
            $i++;
        }
        return $candidate;
    }


}
