<?php
namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Campaign extends Model
{
    use HasFactory, HasSlug;
    protected $table = 'marketing_campaigns';
    protected $fillable = [
        'name', 'slug', 'subject', 'from_name', 'from_email', 
        'content_html', 'template_id', 'status', 'scheduled_at', 
        'sent_at', 'parent_campaign_id', 'variant', 'is_test'
    ];

    public function segments(): BelongsToMany
    {
        return $this->belongsToMany(Segment::class, 'marketing_campaign_segment', 'campaign_id', 'segment_id');
    }

    public function mailingLists(): BelongsToMany
    {
        return $this->belongsToMany(MailingList::class, 'marketing_campaign_mailing_list', 'campaign_id', 'mailing_list_id');
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()->generateSlugsFrom('name')->saveSlugsTo('slug');
    }

    public function getRouteKeyName()
    {
        return 'slug';
    }

    public function emailLogs()
    {
        return $this->hasMany(EmailLog::class, 'campaign_id');
    }
}