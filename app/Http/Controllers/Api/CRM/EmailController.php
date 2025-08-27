<?php

namespace App\Http\Controllers\Api\Crm;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\UserEmailAccount;
use App\Models\Marketing\EmailLog;
use App\Models\Crm\Activity;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Throwable;

class EmailController extends Controller
{
    /**
     * Envía un correo uno-a-uno desde la cuenta conectada del usuario (Symfony Mailer).
     */
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
            // Opcionales
            'from_name'       => 'nullable|string|max:255',
            'reply_to'        => 'nullable|email',
            'text_body'       => 'nullable|string',
            // 'attachments'   => 'nullable|array', // si luego agregas adjuntos
        ]);

        $user = $request->user();
        $account = UserEmailAccount::where('user_id', $user->id)
            ->where('id', $validated['from_account_id'])
            ->firstOrFail();

        // Construir transporte y mailer (Symfony)
        $transport = $this->buildTransport($account);
        $mailer    = new Mailer($transport);

        // Construir el email
        $email     = $this->buildEmail($validated, $user->name ?? $account->email_address, $account);

        try {
            // 1) Enviar
            $mailer->send($email);

            // 2) Registrar log de envío (si tu tabla EmailLog existe / ajustar a tu esquema)
            // Nota: Si necesitas guardar el message-id, puedes extraerlo agregando un MessageEventListener.
            if (class_exists(EmailLog::class)) {
                EmailLog::create([
                    'user_id'     => $user->id,
                    'contact_id'  => $validated['contact_id'],
                    'to'          => $validated['to'],
                    'cc'          => $validated['cc'] ?? [],
                    'bcc'         => $validated['bcc'] ?? [],
                    'subject'     => $validated['subject'],
                    'body'        => $validated['body'],
                    'status'      => 'enviado',
                    'provider'    => 'smtp',
                    'meta'        => [
                        'from'     => $account->email_address,
                        'account'  => $account->id,
                        // 'message_id' => $messageId ?? null, // si implementas listener
                    ],
                ]);
            }

            // 3) Registrar actividad
            if (class_exists(Activity::class)) {
                Activity::create([
                    'contact_id'  => $validated['contact_id'],
                    'type'        => 'correo',
                    'title'       => 'Correo Enviado: ' . $validated['subject'],
                    'description' => strip_tags($validated['body']),
                    'created_by'  => $user->id,
                ]);
            }

            return response()->json(['message' => 'Correo enviado con éxito.']);

        } catch (Throwable $e) {
            // Puedes registrar el error en una columna de tu cuenta si quieres
            // $account->update(['last_send_error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Error al enviar el correo.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Construye el transporte SMTP dinámico a partir de la cuenta del usuario.
     */
    protected function buildTransport(UserEmailAccount $account): EsmtpTransport
    {
        // Si guardas 'none' en la DB, conviértelo a null para Symfony
        $encryption = ($account->smtp_encryption === 'none' || empty($account->smtp_encryption))
            ? null
            : $account->smtp_encryption;

        $transport = new EsmtpTransport(
            $account->smtp_host,
            (int) $account->smtp_port,
            $encryption
        );

        // username y password (suponiendo que $account->password devuelve el valor desencriptado)
        if (!empty($account->smtp_username)) {
            $transport->setUsername($account->smtp_username);
        }
        if (!empty($account->password)) {
            $transport->setPassword($account->password);
        }

        // Opcional: timeouts
        // $transport->setTimeout(10);

        return $transport;
        // Si quieres aceptar self-signed certs para pruebas, podrías usar stream options:
        // $transport->getStream()->setStreamOptions(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
        // ⚠️ No recomendado en producción.
    }

    /**
     * Construye el objeto Email (Symfony Mime) con HTML y opcionales como CC/BCC/Reply-To.
     */
    protected function buildEmail(array $validated, string $fromName, UserEmailAccount $account): Email
    {
        $email = new Email();

        // From (con nombre si viene en request o usa el nombre del usuario autenticado)
        $displayName = $validated['from_name'] ?? $fromName;
        $email->from(new Address($account->email_address, $displayName));

        // To / CC / BCC
        $email->to(...$validated['to']);

        if (!empty($validated['cc'])) {
            $email->cc(...$validated['cc']);
        }
        if (!empty($validated['bcc'])) {
            $email->bcc(...$validated['bcc']);
        }

        // Reply-To opcional
        if (!empty($validated['reply_to'])) {
            $email->replyTo($validated['reply_to']);
        }

        // Subject
        $email->subject($validated['subject']);

        // Cuerpo HTML y opcionalmente texto alternativo
        $email->html($validated['body']);
        if (!empty($validated['text_body'])) {
            $email->text($validated['text_body']);
        }

        // Adjuntos (si luego los agregas desde el request)
        // if (!empty($validated['attachments'])) {
        //     foreach ($validated['attachments'] as $attachment) {
        //         // $attachment: path en disco, o contenido binario + nombre
        //         $email->attachFromPath($attachment);
        //     }
        // }

        return $email;
    }
}
