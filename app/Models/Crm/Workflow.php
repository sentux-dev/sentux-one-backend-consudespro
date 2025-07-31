<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workflow extends Model
{
    use HasFactory;
    protected $table = 'crm_workflows';
    protected $fillable = ['name', 'description', 'is_active', 'priority'];
    
    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function conditions(): HasMany
    {
        return $this->hasMany(WorkflowCondition::class, 'workflow_id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(WorkflowAction::class, 'workflow_id')->orderBy('order');
    }
}