<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;

class ContactCustomFieldValue extends Model
{
    protected $table = 'crm_contact_custom_field_values';

    protected $fillable = [
        'contact_id',
        'custom_field_id',
        'value'
    ];

    // 🔹 Relación con el campo
    public function field()
    {
        return $this->belongsTo(ContactCustomField::class, 'custom_field_id');
    }

    // 🔹 Relación con el contacto
    public function contact()
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function customField()
    {
        return $this->belongsTo(ContactCustomField::class, 'custom_field_id');
    }

    
}