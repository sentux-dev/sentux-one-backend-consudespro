<?php

namespace App\Http\Requests\User\Session;

use Illuminate\Foundation\Http\FormRequest;

class UserSessionFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Asegúrate de permitir el acceso solo si el usuario está autenticado
        return true;
    }

    public function rules(): array
    {
        return [
            'page'      => ['nullable', 'integer', 'min:1'],
            'per_page'  => ['nullable', 'integer', 'min:1', 'max:100'],
            'order_by'  => ['nullable', 'in:created_at,last_activity_at,platform,browser'],
            'order_dir' => ['nullable', 'in:asc,desc'],
            'search'    => ['nullable', 'string', 'max:100'],
        ];
    }

    public function validatedWithDefaults(): array
    {
        $data = $this->validated();

        return [
            'page'      => $data['page'] ?? 1,
            'per_page'  => $data['per_page'] ?? 15,
            'order_by'  => $data['order_by'] ?? 'last_activity_at',
            'order_dir' => $data['order_dir'] ?? 'desc',
            'search'    => $data['search'] ?? null,
        ];
    }
}
