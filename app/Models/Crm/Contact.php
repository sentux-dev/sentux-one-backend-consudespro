<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Str; 

class Contact extends Model
{
    use SoftDeletes;

    protected $table = 'crm_contacts';

    protected $fillable = [
        'first_name',
        'last_name',
        'cellphone',
        'phone',
        'email',
        'contact_status_id',
        'disqualification_reason_id',
        'owner_id',
        'occupation',
        'job_position',
        'current_company',
        'birthdate',
        'address',
        'country',
        'active',
        'unsubscribed_at',
        'subscribed_to_newsletter',
        'subscribed_to_product_updates',
        'subscribed_to_promotions',

    ];

    protected $casts = [
        'active' => 'boolean',
        'unsubscribed_at' => 'datetime',
        'subscribed_to_newsletter' => 'boolean',
        'subscribed_to_product_updates' => 'boolean',
        'subscribed_to_promotions' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function ($contact) {
            if (empty($contact->uuid)) {
                $contact->uuid = (string) Str::uuid();
            }
        });
    }

    /** ============================
     * RELACIONES
     * ============================
     */

    public function status()
    {
        return $this->belongsTo(ContactStatus::class, 'contact_status_id');
    }

    public function companies()
    {
        return $this->belongsToMany(Company::class, 'crm_company_contact', 'contact_id', 'company_id');
    }

    public function disqualificationReason()
    {
        return $this->belongsTo(DisqualificationReason::class, 'disqualification_reason_id');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function customFields()
    {
        return $this->hasMany(ContactCustomField::class, 'contact_id');
    }

    public function deals(): MorphToMany
    {
        return $this->morphToMany(
            Deal::class,                // El modelo final al que nos conectamos
            'associable',               // El prefijo usado en la tabla polimórfica
            'crm_deal_associations',    // El nombre de la tabla de asociaciones
            'associable_id',            // La columna para el ID de este modelo (Contact)
            'deal_id'                   // La columna para el ID del otro modelo (Deal)
        )->withTimestamps()->withPivot('relation_type'); // Incluir campos extra de la tabla pivote
    }

    public function projects()
    {
        return $this->belongsToMany(\App\Models\RealState\Project::class, 'crm_contact_real_state_project', 'crm_contact_id', 'real_state_project_id')
            ->withTimestamps();
    }

    public function entryHistory()
    {
        return $this->hasMany(ContactEntryHistory::class);
    }

    public function campaigns()
    {
        return $this->belongsToMany(Campaign::class, 'crm_contact_entry_history', 'contact_id', 'campaign_id')
                    ->withPivot('entry_at', 'is_original', 'is_last')
                    ->withTimestamps();
    }

    public function origins()
    {
        return $this->belongsToMany(Origin::class, 'crm_contact_entry_history', 'contact_id', 'origin_id')
                    ->withPivot('entry_at', 'is_original', 'is_last')
                    ->withTimestamps();
    }

    public function activities()
    {
        return $this->hasMany(Activity::class, 'contact_id');
    }

    // Accessor para última actividad
    protected $appends = ['last_activity'];

    public function getLastActivityAttribute()
    {
        $lastActivity = $this->activities()->latest('created_at')->first();
        return $lastActivity ? $lastActivity->created_at : null;
    }

    public function customFieldValues()
    {
        return $this->hasMany(ContactCustomFieldValue::class, 'contact_id')
            ->with('field');
    }

    public function associations()
    {
        return $this->hasMany(ContactAssociation::class, 'contact_id');
    }

    public function associatedContacts()
    {
        return $this->belongsToMany(
            Contact::class,
            'crm_contact_associations',
            'contact_id',
            'associated_contact_id'
        )->withPivot('relation_type')->withTimestamps();
    }

    public function dealAssociations(): MorphMany
    {
        return $this->morphMany(DealAssociation::class, 'associable');
    }

    public function scopeApplyPermissions(Builder $query, User $user): Builder
    {
        // 1) Admin: acceso total
        if ($user->hasRole('admin')) {
            return $query;
        }

        $hasViewAll = $user->hasPermissionTo('contacts.view');
        $hasViewOwn = $user->hasPermissionTo('contacts.view.own');

        // 2) Si no tiene ninguno de los dos, no ver nada
        if (!$hasViewAll && !$hasViewOwn) {
            return $query->whereRaw('1 = 0');
        }

        // 3) Si solo tiene "own", limitar a sus propios contactos
        if (!$hasViewAll && $hasViewOwn) {
            return $query->where('owner_id', $user->id);
        }

        // 4) Tiene contacts.view (puede que además tenga own)
        $roleIds = $user->roles->pluck('id');
        $permission = \Spatie\Permission\Models\Permission::where('name', 'contacts.view')->first();

        // Si por alguna razón el registro de permiso no existe, pero tiene 'own', dejamos ver sus propios contactos.
        if (!$permission) {
            return $hasViewOwn ? $query->where('owner_id', $user->id) : $query->whereRaw('1 = 0');
        }

        $rules = \App\Models\PermissionRule::whereIn('role_id', $roleIds)
            ->where('permission_id', $permission->id)
            ->get();

        // 4.a) Sin reglas => ve todo
        if ($rules->isEmpty()) {
            return $query;
        }

        // 4.b) Con reglas => aplicar OR por cada regla.
        // Si además tiene 'own', añadimos otro OR: owner_id = user.id
        return $query->where(function (Builder $q) use ($rules, $user, $hasViewOwn) {
            foreach ($rules as $rule) {
                $value = str_replace('{user.id}', $user->id, $rule->value);

                if ($rule->field_type === 'native') {
                    $q->orWhere($rule->field_name, '=', $value);
                } else { // custom
                    $q->orWhereHas('customFieldValues', function (Builder $subQuery) use ($rule, $value) {
                        $subQuery->whereHas('field', fn($sq) => $sq->where('name', $rule->field_identifier))
                                ->where('value', '=', $value);
                    });
                }
            }

            // Unión con "propios" si también tiene contacts.view.own
            if ($hasViewOwn) {
                $q->orWhere('owner_id', $user->id);
            }
        });
    }


    public function sequenceEnrollments(): HasMany
    {
        return $this->hasMany(ContactSequenceEnrollment::class);
    }
    
}