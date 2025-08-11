<?php
namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sequence extends Model
{
    protected $table = 'crm_sequences';
    protected $fillable = ['name', 'description', 'active'];
    protected $casts = [
        'active' => 'boolean',
    ];

    public function steps(): HasMany
    {
        return $this->hasMany(SequenceStep::class)->orderBy('order');
    }
}