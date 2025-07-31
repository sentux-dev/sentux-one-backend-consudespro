<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'phone',
        'name',
        'email',
        'password',
        'language',
        'date_format',
        'number_format',
        'timezone',
        'time_format',
        'mfa_enabled',
        'mfa_type',
        'mfa_secret',
        'twoFactorEnabled',
        'active',
        'last_active_at'
    ];

    protected $dates = ['deleted_at'];


    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
            'last_active_at' => 'datetime',
            'active' => 'boolean'
        ];
    }

    protected static function booted(): void
    {
        static::saving(function ($user) {
            $user->name = trim("{$user->first_name} {$user->last_name}");
        });
    }

    public function sessions()
    {
        return $this->hasMany(UserSession::class);
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(UserGroup::class, 'user_group_members', 'user_id', 'user_group_id');
    }
    
}
