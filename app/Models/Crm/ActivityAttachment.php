<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ActivityAttachment extends Model
{
    protected $table = 'crm_activity_attachments';

    protected $fillable = [
        'activity_id', 'filename', 'mime_type', 'size', 'path', 'is_inline', 'content_id'
    ];

    // append url
    protected $appends = ['url'];

    public function activity() {
        return $this->belongsTo(Activity::class);
    }

    // URL pública (si usas disk public)
    public function getUrlAttribute(): string {
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('public');
        return $disk->url($this->path);
    }
}
