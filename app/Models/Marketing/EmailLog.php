<?php
namespace App\Models\Marketing;

use App\Models\Crm\Contact;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailLog extends Model
{
    use HasFactory;
    protected $table = 'marketing_email_logs';
    protected $fillable = [
        'campaign_id', 'contact_id', 'provider_message_id', 
        'status', 'error_message', 'opened_at', 'clicked_at', 'meta'
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }
}