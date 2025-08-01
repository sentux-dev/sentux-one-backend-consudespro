<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Contact extends Model
{
    use SoftDeletes;

    protected $table = 'crm_contacts';

    protected $fillable = [
        'first_name',
        'last_name',
        'cellphone',
        'phone',
        'email',
        'contact_status_id',
        'disqualification_reason_id',
        'owner_id',
        'occupation',
        'job_position',
        'current_company',
        'birthdate',
        'address',
        'country',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    /** ============================
     * RELACIONES
     * ============================
     */

    public function status()
    {
        return $this->belongsTo(ContactStatus::class, 'contact_status_id');
    }

    public function disqualificationReason()
    {
        return $this->belongsTo(DisqualificationReason::class, 'disqualification_reason_id');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function customFields()
    {
        return $this->hasMany(ContactCustomField::class, 'contact_id');
    }

    public function deals()
    {
        return $this->belongsToMany(Deal::class, 'crm_contact_crm_deal', 'crm_contact_id', 'crm_deal_id')
            ->withTimestamps();
    }

    public function projects()
    {
        return $this->belongsToMany(\App\Models\RealState\Project::class, 'crm_contact_real_state_project', 'crm_contact_id', 'real_state_project_id')
            ->withTimestamps();
    }

    public function campaigns()
    {
        return $this->belongsToMany(Campaign::class, 'crm_campaign_crm_contact', 'crm_contact_id', 'crm_campaign_id')
            ->withPivot('is_original', 'is_last')
            ->withTimestamps();
    }

    public function origins()
    {
        return $this->belongsToMany(Origin::class, 'crm_origin_crm_contact', 'crm_contact_id', 'crm_origin_id')
            ->withPivot('is_original', 'is_last')
            ->withTimestamps();
    }

    public function activities()
    {
        return $this->hasMany(Activity::class, 'contact_id');
    }

    // Accessor para última actividad
    protected $appends = ['last_activity'];

    public function getLastActivityAttribute()
    {
        $lastActivity = $this->activities()->latest('created_at')->first();
        return $lastActivity ? $lastActivity->created_at : null;
    }

    public function customFieldValues()
    {
        return $this->hasMany(ContactCustomFieldValue::class, 'contact_id')
            ->with('field');
    }

    public function associations()
    {
        return $this->hasMany(ContactAssociation::class, 'contact_id');
    }

    public function associatedContacts()
    {
        return $this->belongsToMany(
            Contact::class,
            'crm_contact_associations',
            'contact_id',
            'associated_contact_id'
        )->withPivot('relation_type')->withTimestamps();
    }

    public function dealAssociations(): MorphMany
    {
        return $this->morphMany(DealAssociation::class, 'associable');
    }
}