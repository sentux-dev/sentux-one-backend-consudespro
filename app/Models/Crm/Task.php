<?php

namespace App\Models\Crm;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $table = 'crm_tasks';

    protected $fillable = [
        'contact_id',
        'owner_id',
        'activity_id',
        'description',
        'status',
        'schedule_date',
        'remember_date',
        'action_type',
        'created_by',
        'updated_by',
        'reminder_sent_at',
    ];

    protected $casts = [
        'schedule_date' => 'datetime',
        'remember_date' => 'datetime',
        'reminder_sent_at' => 'datetime',
    ];


    public function activity()
    {
        return $this->belongsTo(Activity::class, 'activity_id');
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }


    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeApplyPermissions(Builder $query, User $user): Builder
    {
        if ($user->hasRole('admin')) {
            return $query;
        }

        $hasViewAll = $user->hasPermissionTo('tasks.view');
        $hasViewOwn = $user->hasPermissionTo('tasks.view.own');

        if (!$hasViewAll && !$hasViewOwn) {
            return $query->whereRaw('1 = 0'); // No tiene permisos, no ve nada
        }
        
        // Si tiene 'view all' (con o sin 'own'), por ahora le damos acceso a todo.
        // Aquí podrías añadir la lógica de PermissionRule si necesitas filtros más complejos.
        if ($hasViewAll) {
            return $query;
        }
        
        // Si llega aquí, es porque SOLO tiene 'view.own'
        return $query->where('owner_id', $user->id);
    }

}