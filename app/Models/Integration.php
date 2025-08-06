<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Integration extends Model
{
    use HasFactory;
    protected $table = 'integrations';
    protected $fillable = ['provider', 'name', 'credentials', 'is_active'];

    protected $casts = [
        'credentials' => 'encrypted:array', // ¡La magia de la encriptación!
        'is_active' => 'boolean',
    ];
}