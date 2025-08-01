<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class LeadSource extends Model
{
    use HasFactory;
    protected $table = 'crm_lead_sources';
    protected $fillable = ['name', 'source_key', 'api_key', 'allowed_domains', 'is_active'];
    protected $casts = [
        'allowed_domains' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Genera una nueva API Key segura al crear una nueva fuente.
     */
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($source) {
            if (empty($source->api_key)) {
                $source->api_key = Str::random(40);
            }
        });
    }
}