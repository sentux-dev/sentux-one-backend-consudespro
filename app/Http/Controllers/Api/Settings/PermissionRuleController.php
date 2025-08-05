<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Models\PermissionRule;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class PermissionRuleController extends Controller
{
    use AuthorizesRequests;
    /**
     * Lista todas las reglas para un permiso específico dentro de un rol.
     */
    public function index(Role $role, Permission $permission)
    {
        return PermissionRule::where('role_id', $role->id)
            ->where('permission_id', $permission->id)
            ->get();
    }

    /**
     * Crea una nueva regla para un permiso dentro de un rol.
     */
    public function store(Request $request, Role $role, Permission $permission)
    {
        $validated = $request->validate([
            'field_type' => 'required|in:native,custom',
            'field_identifier' => 'required|string',
            'operator' => 'required|string',
            'value' => 'required|string',
        ]);

        $rule = new PermissionRule($validated);
        $rule->role_id = $role->id;
        $rule->permission_id = $permission->id;
        $rule->save();

        return response()->json($rule, 201);
    }

    /**
     * Elimina una regla de permiso.
     */
    public function destroy(PermissionRule $permissionRule)
    {
        $this->authorize('delete', $permissionRule); // Opcional: Crear una Policy para las reglas
        $permissionRule->delete();
        
        return response()->json(['message' => 'Regla eliminada.']);
    }
}