<?php

namespace App\Models\RealState;

use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    protected $table = 'real_state_projects';

    protected $fillable = ['name', 'order', 'active'];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function contacts()
    {
        return $this->belongsToMany(\App\Models\Crm\Contact::class, 'crm_contact_real_state_project', 'real_state_project_id', 'crm_contact_id')
            ->withTimestamps();
    }
}