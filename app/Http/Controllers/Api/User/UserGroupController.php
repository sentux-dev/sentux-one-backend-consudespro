<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\UserGroup;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserGroupController extends Controller
{
    public function index()
    {
        return UserGroup::withCount('users')->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:user_groups,name',
            'description' => 'nullable|string',
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'integer|exists:users,id',
        ]);

        $group = UserGroup::create($validated);

        if (!empty($validated['user_ids'])) {
            $group->users()->sync($validated['user_ids']);
        }

        return response()->json($group->loadCount('users'), 201);
    }

    public function show(UserGroup $userGroup)
    {
        // Cargar los usuarios miembros para la vista de detalle
        return $userGroup->load('users:id,name');
    }

    public function update(Request $request, UserGroup $userGroup)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('user_groups')->ignore($userGroup->id)],
            'description' => 'nullable|string',
            'user_ids' => 'required|array',
        ]);

        $userGroup->update($validated);
        $userGroup->users()->sync($validated['user_ids']);

        return response()->json($userGroup->load('users:id,name'));
    }

    public function destroy(UserGroup $userGroup)
    {
        $userGroup->delete();
        return response()->json(['message' => 'Grupo de usuarios eliminado.']);
    }
}