<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;

class ContactStatus extends Model
{
    protected $table = 'crm_contact_statuses';

    protected $fillable = ['name', 'order', 'active'];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function contacts()
    {
        return $this->hasMany(Contact::class, 'contact_status_id');
    }
}