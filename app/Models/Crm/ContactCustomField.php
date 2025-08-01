<?php

namespace App\Models\CRM;

use Illuminate\Database\Eloquent\Model;

class ContactCustomField extends Model
{
    protected $table = 'crm_contact_custom_fields';

    protected $fillable = [
        'name',
        'label',
        'type',
        'options',
        'active',
    ];

    protected $casts = [
        'options' => 'array',
        'active' => 'boolean',
    ];

    // 🔹 Relación con los valores de los contactos
    public function values()
    {
        return $this->hasMany(ContactCustomFieldValue::class, 'custom_field_id');
    }
}