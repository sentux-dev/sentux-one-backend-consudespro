<?php

namespace App\Policies;

use App\Models\Integration;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class IntegrationPolicy
{
    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Integration $integration): Response // ✅ 2. Cambiar el tipo de retorno a Response
    {
        // ✅ 3. Usar una lógica if/else para devolver una respuesta explícita
        return $user->hasPermissionTo('integrations.delete')
            ? Response::allow()
            : Response::deny('No está autorizado para realizar esta acción.');
    }
}