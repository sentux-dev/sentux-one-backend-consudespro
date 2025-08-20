<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ContactCustomField extends Model
{
    protected $table = 'crm_contact_custom_fields';

    protected $fillable = [
        'name',
        'label',
        'type',
        'options',
        'active',
        'slug'
    ];

    protected static function booted() // ✅ 3. Añadir este método para manejar eventos del modelo
    {
        // Este evento se dispara justo antes de que se guarde un nuevo registro en la BD
        static::creating(function ($model) {
            // Si el campo 'slug' no se ha establecido manualmente,
            // lo generamos a partir del campo 'name'.
            if (empty($model->slug)) {
                $model->slug = Str::slug($model->name);
            }
        });
    }

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