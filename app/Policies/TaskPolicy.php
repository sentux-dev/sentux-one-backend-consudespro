<?php

namespace App\Policies;

use App\Models\Crm\Task;
use App\Models\PermissionRule;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Permission;
use Illuminate\Auth\Access\Response;

class TaskPolicy
{
    /**
     * Hook para dar acceso total a los administradores.
     */
    public function before(User $user, string $ability): bool|null
    {
        if ($user->hasRole('admin')) {
            return true;
        }
        return null;
    }

    /**
     * Determina si el usuario puede ver la lista de tareas.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['tasks.view', 'tasks.view.own']);
    }

    /**
     * Determina si el usuario puede ver una tarea específica.
     */
    public function view(User $user, Task $task): bool
    {
        if ($user->hasPermissionTo('tasks.view')) {
            // Aquí podrías añadir lógica de reglas dinámicas si fuera necesario
            return true;
        }
        if ($user->hasPermissionTo('tasks.view.own')) {
            return $user->id === $task->owner_id;
        }
        return false;
    }

    /**
     * Determina si el usuario puede crear tareas.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('tasks.create');
    }

    /**
     * Determina si el usuario puede actualizar una tarea.
     */
    public function update(User $user, Task $task): bool
    {
        if ($user->hasPermissionTo('tasks.edit')) {
            return true;
        }
        if ($user->hasPermissionTo('tasks.edit.own')) {
            return $user->id === $task->owner_id;
        }
        return false;
    }

    /**
     * Determina si el usuario puede eliminar una tarea.
     */
    public function delete(User $user, Task $task): bool
    {
        if ($user->hasPermissionTo('tasks.delete')) {
            return true;
        }
        if ($user->hasPermissionTo('tasks.delete.own')) {
            return $user->id === $task->owner_id;
        }
        return false;
    }
}