<?php
namespace App\Models\RealState;

use App\Models\Crm\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Lot extends Model
{
    use HasFactory, SoftDeletes, HasSlug;
    protected $table = 'real_state_lots';
    protected $fillable = [
        'project_id', 'lot_number', 'slug', 'house_model_id', 'seller_id', 'formalizer_id',
        'base_price', 'size', 'extra_footage', 'extra_footage_cost',
        'down_payment_percentage', 'reservation_date', 'delivery_date',
        'contract_signing_date', 'contract_due_date', 'house_delivery_date', 'status',
    ];

    public function getSlugOptions(): SlugOptions
    {
        // Genera un slug como "proyecto-x-lote-a-01"
        return SlugOptions::create()
            ->generateSlugsFrom(['project.name', 'lot_number'])
            ->saveSlugsTo('slug');
    }

    public function getRouteKeyName()
    {
        return 'slug';
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function houseModel(): BelongsTo
    {
        return $this->belongsTo(HouseModel::class, 'house_model_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function formalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'formalizer_id');
    }
    
    public function owners(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'real_state_contact_lot', 'lot_id', 'contact_id');
    }

    public function extras(): BelongsToMany
    {
        return $this->belongsToMany(Extra::class, 'real_state_lot_extra', 'lot_id', 'extra_id');
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(LotAdjustment::class, 'lot_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'lot_id');
    }
}