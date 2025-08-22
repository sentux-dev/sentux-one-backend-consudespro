<?php

namespace App\Models\Inventory;

use App\Models\Sales\Product;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryMovement extends Model
{
    use HasFactory;
    protected $table = 'inventory_movements';

    protected $fillable = [
        'product_id', 'quantity_change', 'reason', 'sourceable_id', 'sourceable_type'
    ];

    protected $casts = [
        'quantity_change' => 'decimal:2',
    ];

    // Relaciones
    public function product() { return $this->belongsTo(Product::class); }
    public function sourceable() { return $this->morphTo(); }
}