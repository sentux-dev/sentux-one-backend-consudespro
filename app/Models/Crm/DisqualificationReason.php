<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;

class DisqualificationReason extends Model
{
    protected $table = 'crm_disqualification_reasons';

    protected $fillable = ['name', 'order', 'active'];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function contacts()
    {
        return $this->hasMany(Contact::class, 'disqualification_reason_id');
    }
}