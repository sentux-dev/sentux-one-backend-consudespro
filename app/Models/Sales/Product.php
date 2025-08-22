<?php

namespace App\Models\Sales;

use App\Models\Inventory\InventoryMovement;
use App\Models\Settings\Tax;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;
    protected $table = 'sales_products';

    protected $fillable = [
        'name', 'sku', 'tax_code', 'description', 'unit_price', 'price_includes_tax',
        'is_exempt', 'track_inventory', 'stock_quantity', 'is_active'
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'stock_quantity' => 'decimal:2',
        'price_includes_tax' => 'boolean',
        'is_exempt' => 'boolean',
        'track_inventory' => 'boolean',
        'is_active' => 'boolean',
    ];

    // Relación: Un producto puede tener muchos impuestos predefinidos
    public function taxes()
    {
        return $this->belongsToMany(Tax::class, 'sales_product_tax');
    }

    // Relación: Un producto tiene un historial de movimientos de inventario
    public function inventoryMovements()
    {
        return $this->hasMany(InventoryMovement::class);
    }
}