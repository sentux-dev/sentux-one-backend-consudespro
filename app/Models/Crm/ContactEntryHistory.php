<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;

class ContactEntryHistory extends Model
{
    protected $table = 'crm_contact_entry_history';

    protected $fillable = [
        'contact_id',
        'entry_at',
        'origin_id',
        'campaign_id',
        'external_lead_id',
        'details',
    ];

    protected $casts = [
        'entry_at' => 'datetime',
        'details' => 'array',
    ];

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }
}