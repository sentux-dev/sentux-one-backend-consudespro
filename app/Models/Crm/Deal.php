<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use App\Models\User;

class Deal extends Model
{
    protected $table = 'crm_deals';

    protected $fillable = [
        'name',
        'amount',
        'pipeline_id',
        'stage_id',
        'owner_id',
    ];

    /**
     * Relaciones
     */

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function pipeline()
    {
        return $this->belongsTo(Pipeline::class, 'pipeline_id');
    }

    public function stage()
    {
        return $this->belongsTo(PipelineStage::class, 'stage_id');
    }

    public function customFieldValues()
    {
        return $this->hasMany(DealCustomFieldValue::class, 'deal_id')->with('field');
    }

    public function associations()
    {
        return $this->hasMany(DealAssociation::class, 'deal_id');
    }

    public function contacts(): MorphToMany
    {
        return $this->morphedByMany(Contact::class, 'associable', 'crm_deal_associations')
                    ->withPivot('relation_type')
                    ->withTimestamps();
    }

    public function companies(): MorphToMany
    {
        return $this->morphedByMany(Company::class, 'associable', 'crm_deal_associations')
                    ->withPivot('relation_type')
                    ->withTimestamps();
    }
}