<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;

class ContactCustomField extends Model
{
    protected $table = 'crm_contact_custom_fields';

    protected $fillable = ['contact_id', 'field_key', 'field_value'];

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }
}