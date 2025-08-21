<?php

namespace App\Policies;

use App\Models\PermissionRule;
use App\Models\Crm\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Permission;

class ContactPolicy
{
    public function before(User $user, string $ability): bool|null
    {
        if ($user->hasRole('admin')) {
            return true;
        }
        return null;
    }

    /**
     * Ahora permite si tiene 'contacts.view' O 'contacts.view.own'
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['contacts.view', 'contacts.view.own']);
    }

    /**
     * Aplica reglas dinámicas + soporte para 'contacts.view.own'
     */
    public function scopeContact(Builder $query, User $user): Builder
    {
        // Admin sin filtros, porque llamas a este scope manualmente
        if ($user->hasRole('admin')) {
            return $query;
        }

        $hasViewAll = $user->hasPermissionTo('contacts.view');
        $hasViewOwn = $user->hasPermissionTo('contacts.view.own');

        // Sin permisos -> nada
        if (!$hasViewAll && !$hasViewOwn) {
            return $query->whereRaw('1 = 0');
        }

        // Solo 'own' -> limitar a owner_id = user.id
        if (!$hasViewAll && $hasViewOwn) {
            return $query->where('owner_id', $user->id);
        }

        // Tiene 'contacts.view' (con o sin 'own')
        $roleIds = $user->roles->pluck('id');
        $permission = Permission::where('name', 'contacts.view')->first();

        // Si no existe el permiso en BD por alguna razón
        if (!$permission) {
            return $hasViewOwn ? $query->where('owner_id', $user->id) : $query->whereRaw('1 = 0');
        }

        $rules = PermissionRule::whereIn('role_id', $roleIds)
            ->where('permission_id', $permission->id)
            ->get();

        // Sin reglas => ve todo
        if ($rules->isEmpty()) {
            return $query;
        }

        // Con reglas => OR entre reglas; si además tiene 'own', agregar OR por owner_id = user.id
        return $query->where(function (Builder $q) use ($rules, $user, $hasViewOwn) {
            foreach ($rules as $rule) {
                $value = str_replace('{user.id}', $user->id, $rule->value);
                $operator = $this->getOperatorSymbol($rule->operator);
                $field = $rule->field_identifier;

                if ($rule->field_type === 'native') {
                    $q->orWhere($field, $operator, $value);
                } else { // custom
                    $q->orWhereHas('customFieldValues', function (Builder $subQuery) use ($field, $operator, $value) {
                        $subQuery->whereHas('field', fn($sq) => $sq->where('name', $field))
                                 ->where('value', $operator, $value);
                    });
                }
            }

            if ($hasViewOwn) {
                $q->orWhere('owner_id', $user->id);
            }
        });
    }

    private function getOperatorSymbol(string $operator): string
    {
        return match ($operator) {
            'equals' => '=',
            'not_equals' => '!=',
            'contains' => 'LIKE', // recuerda incluir % en el value desde la regla si lo usas
            default => '=',
        };
    }

    public function create(User $user): bool { return $user->hasPermissionTo('contacts.create'); }
    public function update(User $user, Contact $contact): bool { return $user->hasPermissionTo('contacts.edit'); }
    public function delete(User $user, Contact $contact): bool { return $user->hasPermissionTo('contacts.delete'); }
}
