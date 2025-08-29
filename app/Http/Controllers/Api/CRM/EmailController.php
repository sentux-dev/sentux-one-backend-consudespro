<?php

namespace App\Http\Controllers\Api\Crm;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\UserEmailAccount;
use App\Jobs\SendEmailJob; // ¡Importante! Importar nuestro nuevo Job
use Illuminate\Support\Facades\Storage;

class EmailController extends Controller
{
    public function send(Request $request)
    {
        $validated = $request->validate([
            'from_account_id' => 'required|integer|exists:user_email_accounts,id',
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
            'attachments'     => 'nullable|array|max:5',
            'attachments.*'   => 'file|max:20480',
        ]);

        $user = $request->user();
        
        // Verificamos que la cuenta de correo pertenezca al usuario
        $account = UserEmailAccount::where('user_id', $user->id)
            ->where('id', $validated['from_account_id'])
            ->firstOrFail();

        // 1. Manejar los adjuntos ANTES de despachar el job
        $attachmentData = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                // Guardamos en una carpeta temporal que el job pueda leer
                $path = $file->store('tmp/attachments');
                $attachmentData[] = [
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getClientMimeType(),
                    'size' => $file->getSize(),
                ];
            }
        }

        // 2. Preparamos el array de datos para el Job
        $emailData = [
            'to' => $validated['to'],
            'cc' => $validated['cc'] ?? [],
            'bcc' => $validated['bcc'] ?? [],
            'subject' => $validated['subject'],
            'body' => $validated['body'],
            'from_name' => $validated['from_name'] ?? null,
            'attachments' => $attachmentData,
        ];
        
        // 3. Despachamos el Job a la cola
        SendEmailJob::dispatch($user->id, $account->id, $validated['contact_id'], $emailData);

        // 4. Devolvemos una respuesta inmediata al usuario
        return response()->json(['message' => 'Tu correo ha sido encolado y se enviará en segundo plano.']);
    }

    public function reply(Request $request)
    {
        $validated = $request->validate([
            'from_account_id'     => 'required|integer|exists:user_email_accounts,id',
            'contact_id'          => 'required|integer|exists:crm_contacts,id',
            'parent_activity_id'  => 'required|integer|exists:crm_activities,id',
            'reply_mode'          => 'required|string|in:reply,reply_all',
            'subject'             => 'required|string|max:255',
            'body'                => 'required|string',
            'attachments'         => 'nullable|array|max:5',
            'attachments.*'       => 'file|max:20480',
            // El Job calculará los destinatarios, pero permitimos override
            'to'                  => 'nullable|array',
            'to.*'                => 'email',
            'cc'                  => 'nullable|array',
            'cc.*'                => 'email',
            'bcc'                 => 'nullable|array',
            'bcc.*'               => 'email',
        ]);
        
        $user = $request->user();
        
        $account = UserEmailAccount::where('user_id', $user->id)
            ->where('id', $validated['from_account_id'])
            ->firstOrFail();

        $attachmentData = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('tmp/attachments');
                $attachmentData[] = [
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getClientMimeType(),
                    'size' => $file->getSize(),
                ];
            }
        }
        
        $emailData = [
            'to' => $validated['to'], // El Job deberá recalcular si esto viene vacío
            'cc' => $validated['cc'] ?? [],
            'bcc' => $validated['bcc'] ?? [],
            'subject' => $validated['subject'],
            'body' => $validated['body'],
            'attachments' => $attachmentData,
            'parent_activity_id' => $validated['parent_activity_id'],
            'reply_mode' => $validated['reply_mode'],
        ];

        SendEmailJob::dispatch($user->id, $account->id, $validated['contact_id'], $emailData);

        return response()->json(['message' => 'Tu respuesta ha sido encolada y se enviará en segundo plano.']);
    }
}