<?php

namespace App\Models\Crm;

use App\Models\User;
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
        'updated_by'
    ];

    protected $casts = [
        'schedule_date' => 'datetime',
        'remember_date' => 'datetime',
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
}