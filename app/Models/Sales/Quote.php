<?php

namespace App\Models\Sales;

use App\Models\Crm\Contact;
use App\Models\Settings\Fee;
use App\Models\Settings\IssuingCompany;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Quote extends Model
{
    use HasFactory;
    protected $table = 'sales_quotes';

    protected $fillable = [
        'issuing_company_id', 'contact_id', 'user_id', 'quote_number', 'status',
        'valid_until', 'subtotal', 'discount_type', 'discount_value',
        'tax_details', 'grand_total', 'notes_customer'
    ];

    protected $casts = [
        'valid_until' => 'date',
        'subtotal' => 'decimal:2',
        'discount_value' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'tax_details' => 'array',
    ];

    // Relaciones
    public function issuing_company() { return $this->belongsTo(IssuingCompany::class); }
    public function contact() { return $this->belongsTo(Contact::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function items() { return $this->hasMany(QuoteItem::class); }
    public function fees() { return $this->belongsToMany(Fee::class, 'sales_fee_quote'); }
}