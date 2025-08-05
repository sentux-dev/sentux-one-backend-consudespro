<?php

namespace App\Policies;

use App\Models\PermissionRule;
use App\Models\Crm\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ContactPolicy
{
    /**
     * Hook que se ejecuta antes de cualquier otra comprobación.
     * Si el usuario es un superadministrador, le damos acceso total inmediatamente.
     */
    public function before(User $user, string $ability): bool|null
    {
        // Asumiendo que tienes un rol 'admin' con todos los permisos.
        if ($user->hasRole('admin')) {
            return true;
        }
        return null; // Dejar que las otras reglas de la policy decidan.
    }

    /**
     * Determina si el usuario puede ver la lista de contactos.
     * Este es el método que se usará para filtrar las listas en el controlador.
     * Laravel llama a este método cuando autorizas la acción 'viewAny'.
     */
    public function viewAny(User $user): bool
    {
        // Primer nivel de seguridad: ¿Tiene el permiso base para ver contactos?
        return $user->hasPermissionTo('contacts.view');
    }

    /**
     * Modifica la consulta del constructor de Eloquent para aplicar las reglas dinámicas.
     * Este método es una característica potente de las policies llamada "Query Scoping".
     * Lo llamaremos manualmente desde nuestro controlador.
     */
    public function scopeContact(Builder $query, User $user): Builder
    {
        // Si el usuario no tiene el permiso base, devolvemos una consulta que no encontrará nada.
        if (!$user->hasPermissionTo('contacts.view')) {
            return $query->whereRaw('1 = 0');
        }

        $roleIds = $user->roles->pluck('id');
        $permission = \Spatie\Permission\Models\Permission::where('name', 'contacts.view')->first();

        // Si el permiso 'contacts.view' no existiera, no devolvemos nada.
        if (!$permission) {
            return $query->whereRaw('1 = 0');
        }
        
        $rules = PermissionRule::whereIn('role_id', $roleIds)
            ->where('permission_id', $permission->id)
            ->get();
        
        // Si tiene el permiso pero no hay reglas específicas, puede ver todo.
        if ($rules->isEmpty()) {
            return $query;
        }

        // Si hay reglas, aplicamos la lógica de filtrado.
        // El 'where' principal asegura que el contacto debe cumplir con las reglas.
        return $query->where(function (Builder $q) use ($rules, $user) {
            // Usamos orWhere porque el contacto debe cumplir CUALQUIERA de las reglas definidas
            // para que se le conceda el acceso.
            foreach ($rules as $rule) {
                $value = str_replace('{user.id}', $user->id, $rule->value);
                $operator = $this->getOperatorSymbol($rule->operator);
                $field = $rule->field_identifier;

                if ($rule->field_type === 'native') {
                    // Regla para un campo nativo (ej: owner_id = 1)
                    $q->orWhere($field, $operator, $value);
                } else { // custom
                    // Regla para un campo personalizado
                    $q->orWhereHas('customFieldValues', function (Builder $subQuery) use ($field, $operator, $value) {
                        $subQuery->whereHas('field', fn($sq) => $sq->where('name', $field))
                                 ->where('value', $operator, $value);
                    });
                }
            }
        });
    }
    
    /**
     * Convierte nuestros operadores de texto a símbolos de SQL.
     */
    private function getOperatorSymbol(string $operator): string
    {
        return match ($operator) {
            'equals' => '=',
            'not_equals' => '!=',
            'contains' => 'LIKE', // Nota: el valor deberá incluir '%' si se necesita
            default => '=',
        };
    }

    // Aquí irían las policies para create, update, delete...
    public function create(User $user): bool { return $user->hasPermissionTo('contacts.create'); }
    public function update(User $user, Contact $contact): bool { return $user->hasPermissionTo('contacts.edit'); }
    public function delete(User $user, Contact $contact): bool { return $user->hasPermissionTo('contacts.delete'); }
}