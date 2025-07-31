<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AssignmentCounter extends Model
{
    protected $table = 'crm_assignment_counters';
    protected $fillable = ['countable_id', 'countable_type', 'last_assigned_user_index'];

    public function countable(): MorphTo
    {
        return $this->morphTo();
    }
}