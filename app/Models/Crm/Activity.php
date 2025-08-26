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
        'email_log_id', // ID del log de email asociado (si aplica)
    ];

    protected $casts = [
        'schedule_date' => 'datetime',
        'remember_date' => 'datetime',
        'created_at' => 'datetime',
        'send_invitation' => 'boolean',
    ];

    // cuando consulten enviar un campo adicional date con la fecha de created_at y created_by_name
    protected $appends = ['date', 'created_by_name'];
    public function getDateAttribute()
    {
        return $this->created_at;
    }

    public function getCreatedByNameAttribute()
    {
        // created_by es el id del usuario que creó la actividad
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

    public function emailLog()
    {
        return $this->belongsTo(EmailLog::class, 'email_log_id');
    }
}