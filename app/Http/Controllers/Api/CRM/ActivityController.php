<?php

namespace App\Http\Controllers\Api\CRM;

use App\Http\Controllers\Controller;
use App\Models\Crm\Activity;
use App\Models\Crm\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use App\Mail\MeetingInvitationEmail;

class ActivityController extends Controller
{
    /**
     * Listar actividades por contacto
     */
    public function index($contactId)
    {
        $activities = Activity::where('contact_id', $contactId)
            ->with([
                'emailLog',
                'attachments',
                'contact.owner',
                'creator',
            ])
            ->latest()
            ->get();

        return response()->json([
            'data' => $activities->map(fn ($a) => $this->serializeActivity($a))->values(),
        ]);
    }

    /**
     * Crear nueva actividad
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'contact_id' => ['required', 'exists:crm_contacts,id'],
            'type' => [
                'required',
                Rule::in([
                    'llamada', 'correo', 'tarea', 'reunion', 'nota',
                    'whatsapp', 'sms', 'correoPostal', 'linkedin'
                ])
            ],
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'call_result' => 'nullable|string|max:50',
            'task_action_type' => 'nullable|string|max:50',
            'schedule_date' => 'nullable|date',
            'remember_date' => 'nullable|date',
            'meeting_title' => 'nullable|string|max:255',
            'send_invitation' => 'nullable|boolean',
            'external_guests' => 'nullable|array',
            'external_guests.*' => 'email',
            'action_type' => 'nullable|string|max:50'
        ]);

        // si viene action_type, usarlo como task_action_type
        if (isset($validated['action_type'])) {
            $validated['task_action_type'] = $validated['action_type'];
        }

        // Asignar el usuario autenticado como creador
        $validated['created_by'] = Auth::id();
        $validated['updated_by'] = Auth::id();

        $activity = Activity::create($validated);
        $activity->load(['contact.owner', 'creator', 'emailLog', 'attachments']);

        // --- Enviar invitación si aplica (reunión) ---
        if ($activity->type === 'reunion' && !empty($activity->send_invitation)) {
            $contact = $activity->contact;
            $owner   = $contact?->owner;

            $recipients = [];
            if ($contact?->email) $recipients[] = $contact->email;
            if ($owner?->email)   $recipients[] = $owner->email;
            if (!empty($validated['external_guests'])) {
                $recipients = array_merge($recipients, $validated['external_guests']);
            }
            $uniqueRecipients = array_values(array_unique($recipients));

            if (!empty($uniqueRecipients)) {
                Mail::to($uniqueRecipients)->send(new MeetingInvitationEmail($activity));
            }
        }

        // --- Si es tarea, crear en crm_tasks ---
        if ($activity->type === 'tarea') {
            $task = Task::create([
                'contact_id'    => $activity->contact_id,
                'activity_id'   => $activity->id,
                'description'   => $activity->description ?? null,
                'status'        => 'pendiente',
                'schedule_date' => $activity->schedule_date,
                'remember_date' => $activity->remember_date,
                'action_type'   => $activity->task_action_type,
                'created_by'    => Auth::id(),
                'updated_by'    => Auth::id(),
            ]);

            // si no viene owner_id en la petición, asignar el creador de la tarea
            if (empty($request->owner_id)) {
                $task->owner_id = Auth::id();
                $task->save();
            }
        }

        $activity->refresh()->load(['contact.owner', 'creator', 'emailLog', 'attachments']);

        return response()->json([
            'message' => 'Actividad creada exitosamente',
            'data' => $this->serializeActivity($activity),
        ], 201);
    }

    /**
     * (Opcional) Eliminar actividad
     */
    public function destroy(Activity $activity)
    {
        $activity->delete();

        return response()->json([
            'message' => 'Actividad eliminada correctamente'
        ]);
    }

    /**
     * Serializa una actividad con campos enriquecidos para el frontend.
     */
    private function serializeActivity(Activity $a): array
    {
        $isIncomingEmail = $a->type === 'correo' && is_null($a->created_by);

        // Nombre del creador (si existe)
        $createdByName = $a->created_by ? optional($a->creator)->name : null;

        // Remitente (para correos entrantes): usamos el contacto asociado
        $senderName = null;
        $senderEmail = null;
        if ($isIncomingEmail && $a->relationLoaded('contact') && $a->contact) {
            $senderEmail = $a->contact->email ?? null;
            $senderName  = $this->formatContactName($a);
            if (!$senderName && $senderEmail) {
                $senderName = $senderEmail;
            }
        }

        // Email log (si cargado)
        $emailLog = null;
        if ($a->relationLoaded('emailLog') && $a->emailLog) {
            $emailLog = [
                'status' => $a->emailLog->status,
            ];
        }

        // Adjuntos con URL pública
        $attachments = [];
        if ($a->relationLoaded('attachments')) {
            $attachments = $a->attachments->map(function ($att) {
                return [
                    'id'        => $att->id,
                    'filename'  => $att->filename,
                    'mime_type' => $att->mime_type,
                    'size'      => $att->size,
                    'url'       => Storage::disk('public')->url($att->path),
                    'is_inline' => (bool) $att->is_inline,
                ];
            })->values()->all();
        }

        return [
            'id'                 => $a->id,
            'contact_id'         => $a->contact_id,
            'type'               => $a->type,
            'title'              => $a->title,
            'description'        => $a->description,
            'html_description'   => $a->html_description,
            'has_inline_images'  => (bool) $a->has_inline_images,
            'created_at'         => optional($a->created_at)->toIso8601String(),
            'created_by_name'    => $createdByName,

            // Remitente para correos entrantes
            'sender_name'        => $isIncomingEmail ? $senderName  : null,
            'sender_email'       => $isIncomingEmail ? $senderEmail : null,

            'email_log'          => $emailLog,
            'attachments'        => $attachments,
        ];
    }

    /**
     * Construye el nombre completo del contacto de la actividad (si existe).
     */
    private function formatContactName(Activity $a): ?string
    {
        $c = $a->contact; // requiere ->load('contact') antes
        if (!$c) return null;

        $first = trim((string) ($c->first_name ?? ''));
        $last  = trim((string) ($c->last_name  ?? ''));
        $full  = trim($first . ' ' . $last);

        return $full !== '' ? $full : null;
    }
}
