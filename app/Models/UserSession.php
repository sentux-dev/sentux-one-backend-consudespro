<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSession extends Model
{
    protected $fillable = [
        'user_id',
        'token_id',
        'ip_address',
        'user_agent',
        'device_type',
        'platform',
        'browser',
        'browser_version',
        'location_country',
        'location_region',
        'location_city',
        'is_mobile',
        'is_desktop',
        'last_activity_at',
        'revoked_at',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'is_mobile' => 'boolean',
        'is_desktop' => 'boolean',
        'last_activity_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
// This model represents a user session, including device and location information.
// It is used to track user activity and device details for security and analytics purposes.