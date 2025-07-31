<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowAction extends Model
{
    use HasFactory;
    protected $table = 'crm_workflow_actions';
    protected $fillable = ['workflow_id', 'action_type', 'parameters', 'order'];
    protected $casts = ['parameters' => 'array'];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class, 'workflow_id');
    }
}