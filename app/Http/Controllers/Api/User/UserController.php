<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        $search = $request->get('search');
        $sortField = $request->get('sortField', 'id');
        $sortOrder = $request->get('sortOrder', 'asc'); // asc o desc
        $status = $request->get('status'); // active o inactive

        $query = User::select('id', 'first_name', 'last_name', 'name', 'email', 'active', 'last_active_at', 'created_at')
            ->with('roles:id,name') // Cargar roles relacionados
            ->withCount('roles');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($status !== null && $status !== 'all') {
            $query->where('active', $status === 'active');
        }

        $query->orderBy($sortField, $sortOrder);

        return $query->paginate($perPage);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')],
            'active' => ['required', 'boolean'],
            'role_ids' => 'nullable|array',
            'role_ids.*' => 'integer|exists:roles,id',
        ]);

        $password = $this->generateCustomPassword();

        $user = User::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'name' => "{$validated['first_name']} {$validated['last_name']}",
            'email' => $validated['email'],
            'active' => $validated['active'],
            'password' => Hash::make($password),
        ]);

        if ($request->has('role_ids')) {
            $user->syncRoles($validated['role_ids'] ?? []);
        }

        return response()->json([
            'message' => 'Usuario creado correctamente',
            'data' => $user,
            'generated_password' => $password
        ], 201);
    }

    function generateCustomPassword(): string
    {
        // Generar 4 letras mayúsculas aleatorias
        $letters = strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 4));

        // Generar 4 números aleatorios
        $numbers = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);

        return "{$letters}-{$numbers}";
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'active' => ['required', 'boolean'],
            'password' => ['nullable', 'string', 'min:8'],
            'role_ids' => 'nullable|array',
            'role_ids.*' => 'integer|exists:roles,id',
        ]);

        $validated['first_name'] = trim($validated['first_name']);
        $validated['last_name'] = trim($validated['last_name']);
        $validated['name'] = "{$validated['first_name']} {$validated['last_name']}";

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']); // no actualizamos si no se envía
        }

        $user->update($validated);

        if ($request->has('role_ids')) {
            $user->syncRoles($validated['role_ids'] ?? []);
        }

        return response()->json([
            'message' => 'Usuario actualizado correctamente',
            'data' => $user
        ]);
    }

    public function destroy(User $user)
    {
        try {
            $user->delete();

            if ($user->trashed()) {
                return response()->json([
                    'message' => 'Usuario eliminado correctamente'
                ]);
            }
        } catch (\Throwable $e) {
            // Si falla, lo desactivamos en vez de eliminarlo
            $user->active = false;
            $user->save();

            return response()->json([
                'message' => 'El usuario no pudo ser eliminado por dependencias, fue desactivado.'
            ], 200);
        }
    }
}