<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserEmailAccount extends Model
{
    use HasFactory;

    protected $table = 'user_email_accounts';

    protected $fillable = [
        'user_id',
        'email_address',
        'connection_type',
        'smtp_host',
        'smtp_port',
        'smtp_encryption',
        'smtp_username',
        'imap_host',
        'imap_port',
        'imap_encryption',
        'imap_username',
        'password',
        'is_active',
        'last_sync_at',
        'sync_error_message',
        'last_sync_uid',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password', // Nunca exponer la contraseña encriptada en las respuestas de la API
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'password' => 'encrypted', // ¡Importante! Encripta y desencripta automáticamente
        'is_active' => 'boolean',
        'last_sync_at' => 'datetime',
    ];

    /**
     * Relación con el usuario propietario de la cuenta de correo.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}