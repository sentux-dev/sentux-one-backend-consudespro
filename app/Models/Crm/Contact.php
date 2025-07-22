<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;

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
        'birthdate',
        'address',
        'country',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
        'birthdate' => 'date',
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
}