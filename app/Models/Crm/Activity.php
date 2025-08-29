<?php

namespace App\Models\Crm;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use App\Models\Marketing\EmailLog;

class Activity extends Model
{
    protected $table = 'crm_activities';

    protected $fillable = [
        'contact_id',
        'type',
        'title',
        'description',
        'call_result',
        'task_action_type',
        'schedule_date',
        'remember_date',
        'meeting_title',
        'send_invitation',
        'created_by', // ID del usuario que creó la actividad
        'updated_by', // ID del usuario que actualizó la actividad

        // Email / sincronización
        'email_log_id',           // (si tu esquema lo usa)
        'external_message_id',    // Message-ID del correo
        'html_description',
        'has_inline_images',
        'original_recipients',    // legado: {to:[], cc:[]}

        // Hilo
        'in_reply_to',
        'references',
        'thread_root_message_id',
        'parent_activity_id',
        'sender_name',
        'sender_email',

        // Destinatarios para reply-all futuros
        'email_to',
        'email_cc',
        'email_bcc',
    ];

    protected $casts = [
        'schedule_date'       => 'datetime',
        'remember_date'       => 'datetime',
        'created_at'          => 'datetime',
        'send_invitation'     => 'boolean',
        'has_inline_images'   => 'boolean',
        'original_recipients' => 'array',
        'email_to'            => 'array',
        'email_cc'            => 'array',
        'email_bcc'           => 'array',
    ];

    // Campos calculados
    protected $appends = ['date', 'created_by_name'];

    public function getDateAttribute()
    {
        return $this->created_at;
    }

    public function getCreatedByNameAttribute()
    {
        return $this->created_by ? $this->createdBy->name : null;
    }

    /**
     * Relaciones
     */
    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function task()
    {
        return $this->hasOne(Task::class, 'activity_id');
    }

    /**
     * OJO:
     * Si tu tabla marketing_email_logs relaciona por activity_id, cambia a:
     *   return $this->hasOne(EmailLog::class, 'activity_id');
     * Si sigues usando email_log_id en crm_activities, deja belongsTo.
     */
    public function emailLog()
    {
        return $this->belongsTo(EmailLog::class, 'email_log_id');
    }

    public function attachments()
    {
        return $this->hasMany(ActivityAttachment::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Relaciones de hilo
    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_activity_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_activity_id');
    }
}
