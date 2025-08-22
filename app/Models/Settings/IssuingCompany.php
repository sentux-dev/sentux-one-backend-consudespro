<?php

namespace App\Models\Settings;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class IssuingCompany extends Model
{
    use HasFactory;
    protected $table = 'settings_issuing_companies';

    protected $fillable = [
        'name', 'legal_name', 'tax_id', 'address', 'phone', 'email', 
        'logo_path', 'bank_accounts', 'pdf_header_text', 'pdf_footer_text', 'default_notes'
    ];

    protected $casts = [
        'bank_accounts' => 'array',
    ];

    protected $appends = ['logo_url'];

    public function getLogoUrlAttribute(): ?string
    {
        if ($this->logo_path) {
            /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
            $disk = Storage::disk('public');
            return $disk->url($this->logo_path);
        }
        return null;
    }
}