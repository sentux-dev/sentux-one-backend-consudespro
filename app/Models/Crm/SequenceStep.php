<?php
namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;

class SequenceStep extends Model
{
    protected $table = 'crm_sequence_steps';
    protected $fillable = ['sequence_id', 'order', 'delay_amount', 'delay_unit', 'action_type', 'parameters'];
    protected $casts = ['parameters' => 'array'];

    public function sequence()
    {
        return $this->belongsTo(Sequence::class);
    }
}