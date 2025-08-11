<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;

class Company extends Model
{
    use SoftDeletes;

    protected $table = 'crm_companies';

    protected $fillable = [
        'name',
        'industry',
        'website',
        'phone',
        'email',
        'country',
        'address',
        'owner_id',
    ];

    /**
     * ============================
     * RELACIONES
     * ============================
     */

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function contacts()
    {
        return $this->belongsToMany(Contact::class, 'crm_company_contact', 'company_id', 'contact_id');
    }

    public function deals()
    {
        return $this->belongsToMany(Deal::class, 'crm_company_crm_deal', 'company_id', 'deal_id')
                    ->withTimestamps();
    }

    public function associations()
    {
        return $this->hasMany(ContactAssociation::class, 'associated_contact_id')
                    ->where('association_type', 'companies');
    }

    /**
     * ============================
     * ACCESSORS Y MUTATORS (Opcional)
     * ============================
     */

    public function getDisplayNameAttribute(): string
    {
        return $this->name;
    }
}