<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;

class Deal extends Model
{
    protected $table = 'crm_deals';

    protected $fillable = ['name', 'amount'];

    public function contacts()
    {
        return $this->belongsToMany(Contact::class, 'crm_contact_crm_deal', 'crm_deal_id', 'crm_contact_id')
            ->withTimestamps();
    }
}