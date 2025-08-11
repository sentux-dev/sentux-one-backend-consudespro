<?php
namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;

class ContactSequenceEnrollment extends Model
{
    protected $table = 'crm_contact_sequence_enrollments';
    protected $fillable = ['contact_id', 'sequence_id', 'enrolled_at', 'status', 'current_step', 'next_step_due_at'];
    protected $casts = ['enrolled_at' => 'datetime', 'next_step_due_at' => 'datetime'];

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function sequence()
    {
        return $this->belongsTo(Sequence::class);
    }
}