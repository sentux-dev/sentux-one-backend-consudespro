<?php

namespace App\Models\CRM;

use Illuminate\Database\Eloquent\Model;

class ContactAssociation extends Model
{
    protected $table = 'crm_contact_associations';

    protected $fillable = [
        'contact_id',
        'associated_contact_id',
        'association_type',
        'relation_type'
    ];

    public function contact()
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function associatedContact()
    {
        return $this->belongsTo(Contact::class, 'associated_contact_id');
    }
}