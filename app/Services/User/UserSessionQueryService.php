<?php

namespace App\Services\User;

use Illuminate\Http\Request;
use App\Models\UserSession;
use Illuminate\Pagination\LengthAwarePaginator;

class UserSessionQueryService
{
    public function paginate(Request $request, $userId)
    {
        $query = UserSession::query()->where('user_id', $userId);

        // Filtros simples
        if ($request->filled('device_type')) {
            $query->where('device_type', $request->device_type);
        }

        if ($request->filled('platform')) {
            $query->where('platform', $request->platform);
        }

        if ($request->filled('is_active')) {
            $query->whereNull('revoked_at', $request->boolean('is_active'));
        }

        // Ordenamiento
        $sortBy = $request->get('sort_by', 'last_activity_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginación
        $perPage = $request->integer('per_page', 15);

        return $query->paginate($perPage)->withQueryString();
    }

    public function getUserSessions(
        int $userId,
        string $orderBy = 'last_activity_at',
        string $orderDir = 'desc',
        int $perPage = 15,
        int $page = 1,
        ?string $search = null
    ): LengthAwarePaginator {
        // Lista blanca de columnas permitidas
        $allowedSorts = ['created_at', 'last_activity_at', 'platform', 'browser'];
        if (!in_array($orderBy, $allowedSorts)) {
            $orderBy = 'last_activity_at';
        }

        return UserSession::query()
            ->where('user_id', $userId)
            ->when($search, fn ($q) => $q->where('ip_address', 'like', "%$search%"))
            ->orderBy($orderBy, $orderDir)
            ->paginate($perPage, ['*'], 'page', $page);
    }

}
