<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class UserGroup extends Model
{
    use HasFactory;
    protected $table = 'user_groups';
    protected $fillable = ['name', 'description', 'is_active'];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_group_members', 'user_group_id', 'user_id');
    }
}