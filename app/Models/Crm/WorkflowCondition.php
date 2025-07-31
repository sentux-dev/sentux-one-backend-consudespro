<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowCondition extends Model
{
    use HasFactory;
    protected $table = 'crm_workflow_conditions';
    protected $fillable = ['workflow_id', 'field', 'operator', 'value', 'group_identifier', 'group_logic'];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class, 'workflow_id');
    }
}