<?php

namespace App\Policies;

use App\Models\Crm\Deal;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class DealPolicy
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
     * Determina si el usuario puede ver la lista de deals.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['deals.view', 'deals.view.own']);
    }

    /**
     * Determina si el usuario puede ver un deal específico.
     */
    public function view(User $user, Deal $deal): bool
    {
        if ($user->hasPermissionTo('deals.view')) {
            // Aquí se podrían añadir reglas dinámicas en el futuro si es necesario.
            return true;
        }
        if ($user->hasPermissionTo('deals.view.own')) {
            return $user->id === $deal->owner_id;
        }
        return false;
    }

    /**
     * Determina si el usuario puede crear deals.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('deals.create');
    }

    /**
     * Determina si el usuario puede actualizar un deal.
     */
    public function update(User $user, Deal $deal): bool
    {
        if ($user->hasPermissionTo('deals.edit')) {
            return true;
        }
        if ($user->hasPermissionTo('deals.edit.own')) {
            return $user->id === $deal->owner_id;
        }
        return false;
    }

    /**
     * Determina si el usuario puede eliminar un deal.
     */
    public function delete(User $user, Deal $deal): bool
    {
        if ($user->hasPermissionTo('deals.delete')) {
            return true;
        }
        if ($user->hasPermissionTo('deals.delete.own')) {
            return $user->id === $deal->owner_id;
        }
        return false;
    }
}