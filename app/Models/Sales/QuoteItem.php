<?php

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuoteItem extends Model
{
    use HasFactory;
    protected $table = 'sales_quote_items';

    protected $fillable = [
        'quote_id', 'product_id', 'description', 'quantity', 'unit_price',
        'taxes', 'discount_type', 'discount_value', 'line_total'
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'discount_value' => 'decimal:2',
        'line_total' => 'decimal:2',
        'taxes' => 'array',
    ];

    // Relaciones
    public function quote() { return $this->belongsTo(Quote::class); }
    public function product() { return $this->belongsTo(Product::class); }
}