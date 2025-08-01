<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;

class DealCustomFieldValue extends Model
{
    protected $table = 'crm_deal_custom_field_values';

    protected $fillable = [
        'deal_id',
        'custom_field_id',
        'value',
    ];

    public function field()
    {
        return $this->belongsTo(DealCustomField::class, 'custom_field_id');
    }

    public function deal()
    {
        return $this->belongsTo(Deal::class, 'deal_id');
    }
}