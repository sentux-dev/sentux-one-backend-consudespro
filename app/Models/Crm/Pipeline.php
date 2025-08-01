<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;

class Pipeline extends Model
{
    protected $table = 'crm_pipelines';

    protected $fillable = ['name'];

    public function stages()
    {
        return $this->hasMany(PipelineStage::class, 'pipeline_id')->orderBy('order');
    }

    public function deals()
    {
        return $this->hasMany(Deal::class, 'pipeline_id');
    }
}