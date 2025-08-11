<?php
namespace App\Models\Crm;
use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    protected $table = 'crm_email_templates';
    protected $fillable = ['name', 'subject', 'body', 'active'];
}