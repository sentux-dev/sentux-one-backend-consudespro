<?php
namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExternalLead extends Model
{
    use HasFactory;
    protected $table = 'crm_external_leads'; // 🔹 CORREGIDO
    protected $fillable = ['lead_import_id', 'source', 'payload', 'status', 'received_at', 'processed_at', 'error_message'];
    protected $casts = [
        'payload' => 'array',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function processingLogs(): HasMany
    {
        return $this->hasMany(LeadProcessingLog::class, 'external_lead_id');
    }
}