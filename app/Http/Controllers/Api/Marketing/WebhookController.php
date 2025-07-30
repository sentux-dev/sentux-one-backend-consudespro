<?php

namespace App\Http\Controllers\Api\Marketing;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Marketing\EmailLog;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    /**
     * Maneja los eventos entrantes de los webhooks de Mandrill.
     */
    public function handleMandrill(Request $request)
    {
        // --- Validación de Seguridad de la Firma ---
        if (!$this->verifySignature($request)) {
            Log::warning('Intento de webhook de Mandrill con firma inválida.', [
                'ip' => $request->ip(),
                'signature' => $request->header('X-Mandrill-Signature')
            ]);
            return response('Firma inválida.', 403);
        }

        $events = json_decode($request->input('mandrill_events'));

        if (empty($events)) {
            return response('No events found.', 200);
        }

        foreach ($events as $event) {
            if (!isset($event->msg) || !isset($event->msg->_id)) {
                continue;
            }

            $log = EmailLog::where('provider_message_id', $event->msg->_id)->first();
            
            if ($log) {
                $status = $log->status;
                $errorMessage = null;

                switch ($event->event) {
                    case 'open':
                        $status = 'abierto';
                        $log->opened_at = now();
                        break;
                    case 'click':
                        $status = 'clic';
                        $log->clicked_at = now();
                        break;
                    case 'hard_bounce':
                    case 'soft_bounce':
                        $status = 'rebotado';
                        $errorMessage = $event->msg->bounce_description ?? 'Rebote sin descripción.';
                        break;
                    case 'spam':
                        $status = 'spam';
                        break;
                    case 'reject':
                        $status = 'fallido';
                        $errorMessage = $event->msg->diag ?? 'Rechazado por el servidor.';
                        break;
                }

                $log->status = $status;
                if ($errorMessage) {
                    $log->error_message = $errorMessage;
                }
                $log->save();
            }
        }

        return response('Webhook procesado.', 200);
    }

    /**
     * Verifica la firma del webhook de Mandrill.
     */
    private function verifySignature(Request $request): bool
    {
        $webhookKey = config('services.mandrill.webhook_key');
        if (empty($webhookKey)) {
            Log::error('La clave del webhook de Mandrill no está configurada.');
            return false;
        }

        $signature = $request->header('X-Mandrill-Signature');
        if (empty($signature)) {
            return false;
        }

        // Recrear la firma según la documentación de Mandrill
        $signedData = $request->fullUrl();
        $postData = $request->all();
        ksort($postData);

        foreach ($postData as $key => $value) {
            $signedData .= $key . $value;
        }

        $generatedSignature = base64_encode(hash_hmac('sha1', $signedData, $webhookKey, true));

        return hash_equals($signature, $generatedSignature);
    }
}