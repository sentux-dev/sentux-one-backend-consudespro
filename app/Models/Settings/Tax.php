<?php

namespace App\Models\Settings;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tax extends Model
{
    use HasFactory;
    protected $table = 'settings_taxes';

    protected $fillable = ['name', 'rate', 'type', 'calculation_type'];

    protected $casts = [
        'rate' => 'decimal:2',
    ];
}