<?php

namespace App\Models\Crm;

use App\Models\Crm\Contact;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactAssociation extends Model
{
    use HasFactory;

    protected $table = 'crm_contact_associations';

    protected $fillable = [
        'contact_id',
        'associated_contact_id', // This is for direct Contact-to-Contact associations
        'association_type',
        'relation_type',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    // Relationship for direct contact-to-contact association
    public function associatedContact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'associated_contact_id');
    }
}