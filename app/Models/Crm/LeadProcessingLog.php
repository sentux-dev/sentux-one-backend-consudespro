<?php
namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadProcessingLog extends Model
{
    use HasFactory;
    protected $table = 'crm_lead_processing_logs'; // 🔹 CORREGIDO
    protected $fillable = ['external_lead_id', 'workflow_id', 'action_taken', 'details', 'snapshot'];
    protected $casts = ['snapshot' => 'array'];

    public function externalLead(): BelongsTo
    {
        return $this->belongsTo(ExternalLead::class, 'external_lead_id');
    }
}