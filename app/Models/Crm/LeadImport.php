<?php
namespace App\Models\Crm;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeadImport extends Model
{
    protected $table = 'crm_lead_imports';
    protected $fillable = ['user_id', 'original_file_name', 'status', 'total_rows', 'imported_count', 'mappings'];
    protected $casts = ['mappings' => 'array'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function externalLeads(): HasMany
    {
        return $this->hasMany(ExternalLead::class);
    }
}