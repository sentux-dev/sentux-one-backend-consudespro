<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;

class DealCustomField extends Model
{
    protected $table = 'crm_deal_custom_fields';

    protected $fillable = [
        'name',
        'label',
        'type',
        'options',
        'required',
    ];

    protected $casts = [
        'options' => 'array',
        'required' => 'boolean',
    ];
}