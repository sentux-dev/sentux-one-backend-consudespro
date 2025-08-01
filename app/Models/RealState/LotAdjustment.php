<?php
namespace App\Models\RealState;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LotAdjustment extends Model
{
    use HasFactory;
    protected $table = 'real_state_lot_adjustments';
    protected $fillable = ['lot_id', 'type', 'description', 'amount', 'user_id'];

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class, 'lot_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}