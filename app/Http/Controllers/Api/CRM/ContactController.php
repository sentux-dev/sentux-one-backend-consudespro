<?php

namespace App\Http\Controllers\Api\CRM;

use App\Http\Controllers\Controller;
use App\Http\Resources\CRM\ContactCollection;
use App\Http\Resources\CRM\ContactResource;
use Illuminate\Http\Request;
use App\Models\Crm\Contact;
use App\Models\Crm\Campaign;
use App\Models\Crm\Origin;
use App\Models\Crm\Deal;
use App\Models\RealState\Project;
use App\Models\Log;
use App\Policies\ContactPolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class ContactController extends Controller
{
    use AuthorizesRequests;

    /**
     * Listar contactos con paginación, filtros y relaciones.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Contact::class);
        $user = Auth::user();

        $query = (new ContactPolicy)->scopeContact(Contact::query(), $user);

        $query->with([
            'status', 'disqualificationReason', 'owner', 'deals:id,name',
            'campaigns:id,name', 'origins:id,name', 'projects:id,name'
        ])->withCount(['deals', 'projects', 'campaigns', 'origins']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function (Builder $q) use ($search) {
                $q->where('first_name', 'like', "%$search%")
                  ->orWhere('last_name', 'like', "%$search%")
                  ->orWhere('email', 'like', "%$search%")
                  ->orWhere('cellphone', 'like', "%$search%");
            });
        }

        if ($request->filled('status_id')) $query->where('contact_status_id', $request->status_id);
        if ($request->filled('owner_id')) $query->whereIn('owner_id', is_array($request->owner_id) ? $request->owner_id : [$request->owner_id]);
        if ($request->filled('loss_reason_id')) $query->where('disqualification_reason_id', $request->loss_reason_id);
        
        if ($request->filled('project_id')) $query->whereHas('projects', fn($q) => $q->where('real_state_projects.id', $request->project_id));
        if ($request->filled('campaign_id')) $query->whereHas('campaigns', fn($q) => $q->where('crm_campaigns.id', $request->campaign_id));
        if ($request->filled('origin_id')) $query->whereHas('origins', fn($q) => $q->where('crm_origins.id', $request->origin_id));

        foreach ($request->all() as $key => $value) {
            if (str_starts_with($key, 'cf_') && !empty($value)) {
                $customFieldName = substr($key, 3);
                $query->whereHas('customFieldValues', function (Builder $q) use ($customFieldName, $value) {
                    $q->where('value', $value)
                      ->whereHas('field', fn(Builder $sq) => $sq->where('name', $customFieldName));
                });
            }
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
        }

        $sortField = $request->get('sort_field', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        
        if ($sortField === 'full_name') {
            $query->orderBy('first_name', $sortOrder)->orderBy('last_name', $sortOrder);
        } else {
            $query->orderBy($sortField, $sortOrder);
        }

        $contacts = $query->paginate($request->get('per_page', 10));
        return new ContactCollection($contacts);
    }

    /**
     * Crear un nuevo contacto.
     */
    public function store(Request $request)
    {
        // ✅ Validación actualizada: ahora espera IDs individuales, no arrays.
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'required|email|unique:crm_contacts,email',
            'cellphone' => 'nullable|string|max:50',
            'contact_status_id' => 'required|exists:crm_contact_statuses,id',
            'owner_id' => 'nullable|exists:users,id',
            'projects' => 'nullable|array', // Se mantiene como array si un contacto puede tener varios proyectos
            
            // ✅ Cambiado a singular y a 'integer'
            'campaign_id' => 'nullable|integer|exists:crm_campaigns,id',
            'origin_id' => 'nullable|integer|exists:crm_origins,id',

            'custom_fields' => 'nullable|array',
            'custom_fields.*.field_id' => 'required|exists:crm_contact_custom_fields,id',
            'custom_fields.*.value' => 'nullable|string|max:65535',
        ]);

        DB::beginTransaction();
        try {
            // Se crea el contacto con los campos principales
            $contact = Contact::create($validated);

            // Guardar campos personalizados
            if (!empty($validated['custom_fields'])) {
                foreach ($validated['custom_fields'] as $cf) {
                    if (!is_null($cf['value'])) {
                        $contact->customFieldValues()->create([
                            'custom_field_id' => $cf['field_id'],
                            'value' => $cf['value']
                        ]);
                    }
                }
            }
            
            // Guardar proyectos (se mantiene igual)
            if (!empty($validated['projects'])) {
                $contact->projects()->attach($validated['projects']);
            }

            // ✅ --- LÓGICA DE CAMPAÑA SIMPLIFICADA ---
            if (!empty($validated['campaign_id'])) {
                // Al ser la primera, es tanto la original como la última.
                $contact->campaigns()->attach($validated['campaign_id'], [
                    'is_original' => true,
                    'is_last' => true
                ]);
            }

            // ✅ --- LÓGICA DE ORIGEN SIMPLIFICADA ---
            if (!empty($validated['origin_id'])) {
                // Al ser el primero, es tanto el original como el último.
                $contact->origins()->attach($validated['origin_id'], [
                    'is_original' => true,
                    'is_last' => true
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Contacto creado correctamente',
                'contact' => $contact->load(['status', 'owner', 'projects', 'campaigns', 'origins'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al crear contacto', 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Ver un contacto específico.
     */
    public function show(Contact $contact)
    {
        $user = Auth::user();
        $query = (new ContactPolicy)->scopeContact(Contact::query(), $user);
        $query->with([
            'status', 'disqualificationReason', 'owner', 'deals:id,name',
            'campaigns:id,name', 'origins:id,name', 'projects:id,name'
        ])->withCount(['deals', 'projects', 'campaigns', 'origins']);

        return new ContactResource($query->findOrFail($contact->id));
    }

    /**
     * Actualiza un contacto completo (PUT).
     */
    public function update(Request $request, Contact $contact)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => "required|email|unique:crm_contacts,email,{$contact->id}",
            'projects' => 'sometimes|array',
            'campaigns' => 'sometimes|array',
            'origins' => 'sometimes|array',
        ]);

        DB::beginTransaction();
        try {
            $contact->update($validated);

            if ($request->has('projects')) {
                $contact->projects()->sync($validated['projects'] ?? []);
            }
            if ($request->has('campaigns')) {
                $this->updateAssociationHistory($contact, 'campaigns', $validated['campaigns'] ?? []);
            }
            if ($request->has('origins')) {
                $this->updateAssociationHistory($contact, 'origins', $validated['origins'] ?? []);
            }

            DB::commit();
            return response()->json(['message' => 'Contacto actualizado correctamente', 'contact' => $contact->fresh()->load('projects', 'campaigns', 'origins')]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al actualizar contacto', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Actualiza campos específicos de un contacto (PATCH).
     */
    public function updatePatch(Request $request, Contact $contact): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'email' => "sometimes|email|unique:crm_contacts,email,{$contact->id}",
            'campaigns' => 'sometimes|array',
            'origins' => 'sometimes|array',
        ]);

        try {
            DB::beginTransaction();
            
            $contact->fill($request->only([
                'first_name', 'last_name', 'phone', 'cellphone', 'email', 'occupation', 
                'job_position', 'birthdate', 'contact_status_id', 'owner_id', 
                'disqualification_reason_id', 'address', 'country',
            ]));
            
            if ($request->has('campaigns')) {
                $this->updateAssociationHistory($contact, 'campaigns', $validated['campaigns']);
            }
            if ($request->has('origins')) {
                $this->updateAssociationHistory($contact, 'origins', $validated['origins']);
            }
            
            $contact->save();
            DB::commit();
            
            $contact->load(['status', 'disqualificationReason', 'owner', 'deals', 'campaigns', 'origins', 'projects']);
            return (new ContactResource($contact))->response()->setStatusCode(200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al actualizar el contacto', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Añade una nueva entrada al historial de asociaciones (Campañas u Orígenes).
     */
    public function addAssociationHistory(Request $request, Contact $contact)
    {
        $validated = $request->validate([
            'type' => ['required', 'string', \Illuminate\Validation\Rule::in(['campaigns', 'origins'])],
            'source_id' => ['required', 'integer'],
        ]);

        $relationName = $validated['type'];
        $sourceId = $validated['source_id'];
        $relation = $contact->{$relationName}();

        if (!$relation->getRelated()->where('id', $sourceId)->exists()) {
            return response()->json(['message' => 'El ID de la fuente proporcionada no es válido.'], 422);
        }
        
        DB::beginTransaction();
        try {
            $relation->newPivotQuery()->update(['is_last' => false]);
            $relation->attach($sourceId, ['is_original' => false, 'is_last' => true]);
            DB::commit();
            return response()->json(['message' => 'Se ha añadido una nueva entrada al historial del contacto.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al añadir la entrada al historial.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Devuelve el historial de asociaciones para un contacto.
     */
    public function getAssociationHistory(Contact $contact, string $type)
    {
        if (!in_array($type, ['campaigns', 'origins'])) {
            return response()->json(['message' => 'Tipo de asociación no válido.'], 400);
        }

        $history = $contact->{$type}()
            ->withPivot('created_at', 'is_original', 'is_last')
            ->orderBy('pivot_created_at', 'asc')
            ->get();
            
        $formattedHistory = $history->map(function ($item) {
            return [
                'name' => $item->name,
                'assigned_at' => $item->pivot->created_at->toDateTimeString(),
                'is_original' => (bool)$item->pivot->is_original,
                'is_last' => (bool)$item->pivot->is_last,
            ];
        });
        return response()->json($formattedHistory);
    }
    
    /**
     * Lógica centralizada para actualizar el historial de asociaciones.
     */
    private function updateAssociationHistory(Contact $contact, string $relationName, array $newIds): void
    {
        $relation = $contact->{$relationName}();
        $existingIds = $relation->pluck($relation->getRelated()->getTable().'.id')->toArray();

        $idsToAdd = array_diff($newIds, $existingIds);
        $idsToRemove = array_diff($existingIds, $newIds);

        if (!empty($idsToRemove)) {
            $relation->detach($idsToRemove);
        }
        if (!empty($idsToAdd)) {
            $attachData = [];
            foreach ($idsToAdd as $id) {
                $attachData[$id] = ['is_original' => false, 'is_last' => false];
            }
            $relation->attach($attachData);
        }

        $relation->newPivotQuery()->update(['is_last' => false]);
        
        $latestAssociation = $relation->orderBy('pivot_created_at', 'desc')->first();
        if ($latestAssociation) {
            $relation->updateExistingPivot($latestAssociation->id, ['is_last' => true]);
        }
    }
 

    /**
     * Actualiza los datos básicos de un contacto.
     */

    public function updateBasic(Request $request, Contact $contact)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'position' => 'nullable|string|max:255',
        ]);

        $contact->update($validated);
        $contact->load(['status', 'disqualificationReason', 'owner', 'deals', 'campaigns', 'origins', 'projects']);

        return new ContactResource($contact);
    }

    /**
     * Eliminar o desactivar un contacto.
     */
    public function destroy(Contact $contact)
    {
        DB::beginTransaction();
        if ($contact->trashed()) {
            return response()->json(['message' => 'El contacto ya está eliminado o desactivado'], 409);
        }
        try {
            if ($contact->deals()->exists() || $contact->projects()->exists() || $contact->campaigns()->exists()) {
                $contact->active = false;
                $contact->save();
                $message = 'Contacto desactivado correctamente por tener relaciones activas';
            } else {
                $contact->delete();
                $message = 'Contacto eliminado correctamente';
            }

            $this->logAction('delete_contact', 'Contact', $contact->id, ['message' => $message]);

            DB::commit();
            return response()->json(['message' => $message]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al eliminar contacto', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Reactivar un contacto (cuando reingresa).
     */
    public function reactivate(Request $request, Contact $contact)
    {
        $validated = $request->validate([
            'campaign_id' => 'nullable|exists:crm_campaigns,id',
            'origin_id' => 'nullable|exists:crm_origins,id',
        ]);

        $contact->active = true;
        $contact->save();

        // Actualizar última campaña y origen si se envían
        if (!empty($validated['campaign_id'])) {
            $contact->campaigns()->updateExistingPivot($validated['campaign_id'], [
                'is_last' => true
            ]);
        }

        if (!empty($validated['origin_id'])) {
            $contact->origins()->updateExistingPivot($validated['origin_id'], [
                'is_last' => true
            ]);
        }

        $this->logAction('reactivate_contact', 'Contact', $contact->id, [
            'message' => 'Contacto reactivado',
            'campaign_id' => $validated['campaign_id'] ?? null,
            'origin_id' => $validated['origin_id'] ?? null,
        ]);

        return response()->json(['message' => 'Contacto reactivado correctamente']);
    }

    /**
     * Eliminar una asociación de contacto.
     */
    public function deleteAssociation(Contact $contact, $id)
    {
        $association = $contact->associations()->where('id', $id)->firstOrFail();
        $association->delete();

        return response()->json(['message' => 'Asociación eliminada correctamente.']);
    }

    /**
     * Registrar un log genérico.
     */
    private function logAction(string $action, string $entityType, ?int $entityId, $changes = null)
    {
        Log::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'changes' => $changes ? json_encode($changes) : null,
        ]);
    }

}