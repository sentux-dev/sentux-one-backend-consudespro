<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;

class PipelineStage extends Model
{
    protected $table = 'crm_pipeline_stages';

    protected $fillable = ['pipeline_id', 'name', 'order'];

    public function pipeline()
    {
        return $this->belongsTo(Pipeline::class, 'pipeline_id');
    }
}