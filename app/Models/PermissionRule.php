<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionRule extends Model
{
    use HasFactory;
    protected $table = 'permission_rules';
    protected $fillable = [
        'role_id',
        'permission_id',
        'field_type',
        'field_identifier',
        'operator',
        'value',
    ];

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function permission()
    {
        return $this->belongsTo(Permission::class);
    }
}