<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;

class Campaign extends Model
{
    protected $table = 'crm_campaigns';

    protected $fillable = ['name', 'order', 'active'];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function contacts()
    {
        return $this->belongsToMany(Contact::class, 'crm_campaign_crm_contact', 'crm_campaign_id', 'crm_contact_id')
            ->withPivot('is_original', 'is_last')
            ->withTimestamps();
    }
}