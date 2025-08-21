<?php

namespace App\Http\Controllers\Api\CRM;

use App\Http\Controllers\Controller;
use App\Models\Crm\Contact;
use Illuminate\Http\Request;
use App\Models\Crm\Task;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;

class TaskController extends Controller
{
    use AuthorizesRequests; // ✅ Añadir el trait

    /**
     * ✅ Listar tareas (vista tipo tabla general)
     * Paginación y filtros.
     */
    public function listTasks(Request $request)
    {
        // 1. Autorización: ¿Puede el usuario ver la lista de tareas?
        $this->authorize('viewAny', Task::class);

        // 2. Aplicar el scope de permisos para filtrar la consulta base
        $query = Task::query()->applyPermissions(Auth::user());

        // 3. Cargar relaciones y aplicar filtros de la petición
        $query->with(['activity', 'createdBy', 'owner', 'contact']);

        // ✅ Filtro por estado
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // ✅ Filtro por tipo de tarea (en la tarea o en la actividad asociada)
        if ($request->filled('type')) {
            $query->where(function ($q) use ($request) {
                $q->where('action_type', $request->type);
            });
        }

        // ✅ Filtro por propietario (usuario que creó la tarea)
        if ($request->filled('owner_id')) {
            $query->where('owner_id', $request->owner_id);
        }

        // ✅ Filtro por rango de fechas (schedule_date o created_at si no hay schedule_date)
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->where(function ($q) use ($request) {
                $q->whereBetween('schedule_date', [$request->start_date, $request->end_date])
                ->orWhere(function ($qa) use ($request) {
                    $qa->whereNull('schedule_date')
                        ->whereBetween('created_at', [$request->start_date, $request->end_date]);
                });
            });
        }

        // ✅ Filtro de búsqueda global (en descripción de la tarea y título/descripcion de la actividad)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                ->orWhereHas('activity', function ($qa) use ($search) {
                    $qa->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            });
        }

       // ✅ Ordenamiento dinámico
         if ($request->filled('sort_field')) {
            $direction = $request->get('sort_order', 'asc') == -1 ? 'desc' : 'asc';
            $sortField = $request->sort_field;

            // Para campos de tablas relacionadas, hacemos un join seguro
            if ($sortField === 'contact.name') {
                $query->join('crm_contacts', 'crm_tasks.contact_id', '=', 'crm_contacts.id')
                      ->select('crm_tasks.*') // Es crucial seleccionar explícitamente las columnas de la tabla principal
                      ->orderBy('crm_contacts.first_name', $direction)
                      ->orderBy('crm_contacts.last_name', $direction);
            } elseif ($sortField === 'owner.name') {
                $query->join('users as owners', 'crm_tasks.owner_id', '=', 'owners.id')
                      ->select('crm_tasks.*')
                      ->orderBy('owners.name', $direction);
            } elseif ($sortField === 'created_by.name') {
                 $query->join('users as creators', 'crm_tasks.created_by', '=', 'creators.id')
                       ->select('crm_tasks.*')
                       ->orderBy('creators.name', $direction);
            } else {
                // Para campos de la tabla principal, el ordenamiento es directo
                $query->orderBy($sortField, $direction);
            }
        } else {
            // Orden por defecto
            $query->orderBy('created_at', 'desc');
        }

        // ✅ Paginación con metadatos completos (estándar de Laravel)
        $tasks = $query->latest()->paginate($request->get('per_page', 10));

        return response()->json([
            'data' => $tasks->items(),
            'meta' => [
                'total' => $tasks->total(),
                'current_page' => $tasks->currentPage(),
                'last_page' => $tasks->lastPage(),
                'per_page' => $tasks->perPage()
            ]
        ]);
    }

    /**
     * ✅ Listar tareas específicas de un contacto (uso en el Tab de Tareas de Contact Detail)
     */
    public function listTasksByContact(Request $request, $contactId)
    {
        $this->authorize('view', Contact::findOrFail($contactId));

        $query = Task::with(['activity', 'createdBy', 'owner', 'contact'])
            ->where(function ($query) use ($contactId) {
                $query->whereHas('activity', function ($q) use ($contactId) {
                    $q->where('contact_id', $contactId);
                })
                ->orWhereNull('activity_id'); // Tareas independientes
            });

        // ✅ Filtro por estado
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // ✅ Filtro por tipo (se filtra en la actividad relacionada)
        if ($request->filled('type')) {
            $query->whereHas('activity', function ($q) use ($request) {
                $q->where('task_action_type', $request->type)
                ->orWhere('action_type', $request->type);
            });
        }

        // ✅ Filtro por rango de fechas (schedule_date)
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('schedule_date', [$request->start_date, $request->end_date]);
        }

        // ✅ Ordenar por más recientes
        $tasks = $query->latest()->get();

        return response()->json([
            'data' => $tasks
        ]);
    }

    /**
     * ✅ Crear tarea (independiente o ligada a una actividad).
     */
    public function createTask(Request $request)
    {
        $validated = $request->validate([
            'activity_id' => 'nullable|exists:crm_activities,id',
            'description' => 'nullable|string',
            'status' => 'nullable|in:pendiente,completada,vencida',
            'schedule_date' => 'nullable|date',
            'remember_date' => 'nullable|date',
            'action_type' => 'nullable|string',
            'contact_id' => 'nullable|exists:crm_contacts,id',
            'owner_id' => 'nullable|exists:users,id',
        ]);

        $task = Task::create([
            'activity_id' => $validated['activity_id'] ?? null,
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'] ?? 'pendiente',
            'schedule_date' => $validated['schedule_date'] ?? null,
            'remember_date' => $validated['remember_date'] ?? null,
            'action_type' => $validated['action_type'] ?? null,
            'contact_id' => $validated['contact_id'] ?? null,
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
            'owner_id' => $validated['owner_id'] ?? Auth::id()
        ]);       

        return response()->json([
            'message' => 'Tarea creada exitosamente',
            'data' => $task->load('activity', 'createdBy')
        ]);
    }

    /**
     * ✅ Ver tarea específica (detalle).
     * Usamos Route-Model Binding para encontrar la tarea automáticamente.
     */
    public function show(Task $task)
    {
        $task->load(['activity', 'createdBy', 'owner', 'contact']);
        
        return response()->json([
            'data' => $task
        ]);
    }

    /**
     * ✅ Actualizar tarea (PATCH).
     * Permite actualizar campos específicos de la tarea.
     */
    public function updateTask(Request $request, Task $task)
    {
        $validated = $request->validate([
            'description'   => 'sometimes|nullable|string',
            'status'        => 'sometimes|in:pendiente,completada,vencida',
            'schedule_date' => 'sometimes|nullable|date',
            'remember_date' => 'sometimes|nullable|date',
            'action_type'   => 'sometimes|nullable|string',
            'owner_id'      => 'sometimes|nullable|exists:users,id',
        ]);

        $task->update(array_merge($validated, [
            'updated_by' => Auth::id()
        ]));

        $task->load(['activity', 'createdBy', 'owner', 'contact']);

        return response()->json([
            'message' => 'Tarea actualizada exitosamente',
            'data' => $task
        ]);
    }

    /**
     * ✅ Eliminar tarea (DELETE).
     * Permite eliminar una tarea específica.
     */
    public function deleteTask(Task $task)
    {
        $task->delete();
        return response()->json([
            'message' => 'Tarea eliminada correctamente'
        ]);
    }
}