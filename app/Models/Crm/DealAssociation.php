<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo; 

class DealAssociation extends Model
{
    protected $table = 'crm_deal_associations';

    protected $fillable = [
        'deal_id',         
        'associable_id',
        'associable_type',
        'relation_type',
    ];

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class, 'deal_id');
    }

    public function associable(): MorphTo
    {
        return $this->morphTo();
    }
}