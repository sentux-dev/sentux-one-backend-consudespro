<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;

class Origin extends Model
{
    protected $table = 'crm_origins';

    protected $fillable = ['name', 'order', 'active'];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function contacts()
    {
        return $this->belongsToMany(Contact::class, 'crm_origin_crm_contact', 'crm_origin_id', 'crm_contact_id')
            ->withPivot('is_original', 'is_last')
            ->withTimestamps();
    }
}