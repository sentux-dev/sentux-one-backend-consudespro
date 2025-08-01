<?php
namespace App\Models\Marketing;

use App\Models\Crm\Contact;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MailingList extends Model
{
    use HasFactory;
    protected $table = 'marketing_mailing_lists';
    protected $fillable = ['name', 'description'];

    public function contacts()
    {
        return $this->belongsToMany(Contact::class, 'marketing_list_contact', 'mailing_list_id', 'contact_id');
    }
}