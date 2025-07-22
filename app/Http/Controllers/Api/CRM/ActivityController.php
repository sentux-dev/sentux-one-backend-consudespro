<?php

namespace App\Http\Controllers\Api\CRM;

use App\Http\Controllers\Controller;
use App\Models\CRM\Activity;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ActivityController extends Controller
{
    /**
     * Listar actividades por contacto
     */
    public function index($contactId)
    {
        $activities = Activity::where('contact_id', $contactId)
            ->latest()
            ->get();

        return response()->json([
            'data' => $activities
        ]);
    }

    /**
     * Crear nueva actividad
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'contact_id' => ['required', 'exists:crm_contacts,id'],
            'type' => [
                'required',
                Rule::in([
                    'llamada', 'correo', 'tarea', 'reunion', 'nota',
                    'whatsapp', 'sms', 'correoPostal', 'linkedin'
                ])
            ],
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'call_result' => 'nullable|string|max:50',
            'task_action_type' => 'nullable|string|max:50',
            'schedule_date' => 'nullable|date',
            'remember_date' => 'nullable|date',
            'meeting_title' => 'nullable|string|max:255'
        ]);

        // Asignar el usuario autenticado como creador de la actividad
        $validated['created_by'] = auth()->id();
        $validated['updated_by'] = auth()->id(); // También asignamos el actualizador

        $activity = Activity::create($validated);

        return response()->json([
            'message' => 'Actividad creada exitosamente',
            'data' => $activity
        ], 201);
    }

    /**
     * (Opcional) Eliminar actividad
     */
    public function destroy(Activity $activity)
    {
        $activity->delete();

        return response()->json([
            'message' => 'Actividad eliminada correctamente'
        ]);
    }
}