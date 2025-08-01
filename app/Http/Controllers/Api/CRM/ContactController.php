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
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ContactController extends Controller
{
    /**
     * Listar contactos con paginación, filtros y relaciones.
     */
    public function index(Request $request)
    {
        $query = Contact::with([
            'status',
            'disqualificationReason',
            'owner',
            'deals:id,name',
            'campaigns:id,name',
            'origins:id,name',
            'projects:id,name'
        ])->withCount(['deals', 'projects', 'campaigns', 'origins']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%$search%")
                    ->orWhere('last_name', 'like', "%$search%")
                    ->orWhere('email', 'like', "%$search%")
                    ->orWhere('cellphone', 'like', "%$search%");
            });
        }

        if ($request->filled('status_id')) {
            $query->where('contact_status_id', $request->status_id);
        }

        if ($request->filled('owner_id')) {
            $query->where('owner_id', $request->owner_id);
        }

        if ($request->filled('project_id')) {
            // Cuando trabaje el modulo de real state
        }

        if ($request->filled('campaign_id')) {
            $query->whereHas('campaigns', function ($q) use ($request) {
                $q->where('crm_campaigns.id', $request->campaign_id);
            });
        }

        if ($request->filled('origin_id')) {
            $query->whereHas('origins', function ($q) use ($request) {
                $q->where('crm_origins.id', $request->origin_id);
            });
        }

        if ($request->filled('loss_reason_id')) {
            $query->where('disqualification_reason_id', $request->loss_reason_id);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
        }

        $sortField = $request->get('sort_field', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');

        // Si el campo es full_name, ordenamos por first_name y last_name
        if ($sortField === 'full_name') {
            $query->orderBy('first_name', $sortOrder)
                ->orderBy('last_name', $sortOrder);
        } else if ($sortField === 'status_name') {
            $query->join('crm_contact_statuses', 'crm_contacts.contact_status_id', '=', 'crm_contact_statuses.id')
                ->orderBy('crm_contact_statuses.name', $sortOrder);
        } else if ($sortField === 'owner_name') {
            $query->join('users as owners', 'crm_contacts.owner_id', '=', 'owners.id')
                ->orderBy('owners.first_name', $sortOrder)
                ->orderBy('owners.last_name', $sortOrder);
        } else if ($sortField === 'deals_names') {
            $query->leftJoin('crm_contact_crm_deal', 'crm_contacts.id', '=', 'crm_contact_crm_deal.crm_contact_id')
                ->leftJoin('crm_deals', 'crm_contact_crm_deal.crm_deal_id', '=', 'crm_deals.id')
                ->select('crm_contacts.*')
                ->distinct()
                ->orderBy('crm_deals.name', $sortOrder);
        } else if ($sortField === 'campaigns_names') {
            $query->leftJoin('crm_campaign_crm_contact', 'crm_contacts.id', '=', 'crm_campaign_crm_contact.crm_contact_id')
                ->leftJoin('crm_campaigns', 'crm_campaign_crm_contact.crm_campaign_id', '=', 'crm_campaigns.id')
                ->select('crm_contacts.*')
                ->distinct()
                ->orderBy('crm_campaigns.name', $sortOrder);
        } else if ($sortField === 'origins_names') {
            $query->leftJoin('crm_origin_crm_contact', 'crm_contacts.id', '=', 'crm_origin_crm_contact.crm_contact_id')
                ->leftJoin('crm_origins', 'crm_origin_crm_contact.crm_origin_id', '=', 'crm_origins.id')
                ->select('crm_contacts.*')
                ->distinct()
                ->orderBy('crm_origins.name', $sortOrder);
        } else if ($sortField === 'projects_names') {
            $query->leftJoin('crm_contact_real_state_project', 'crm_contacts.id', '=', 'crm_contact_real_state_project.crm_contact_id')
                ->leftJoin('real_state_projects', 'crm_contact_real_state_project.real_state_project_id', '=', 'real_state_projects.id')
                ->select('crm_contacts.*')
                ->distinct()
                ->orderBy('real_state_projects.name', $sortOrder);
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
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'cellphone' => 'nullable|string|max:50',
            'phone' => 'nullable|string|max:50',
            'email' => 'required|email|unique:crm_contacts,email',
            'contact_status_id' => 'required|exists:crm_contact_statuses,id',
            'disqualification_reason_id' => 'nullable|exists:crm_disqualification_reasons,id',
            'owner_id' => 'nullable|exists:users,id',
            'occupation' => 'nullable|string|max:255',
            'job_position' => 'nullable|string|max:255',
            'birthdate' => 'nullable|date',
            'address' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',

            // Relaciones
            'deals' => 'nullable|array',
            'deals.*' => 'integer|exists:crm_deals,id',

            'projects' => 'nullable|array',
            'projects.*' => 'integer|exists:real_state_projects,id',

            'campaigns' => 'nullable|array',
            'campaigns.*' => 'integer|exists:crm_campaigns,id',

            'origins' => 'nullable|array',
            'origins.*' => 'integer|exists:crm_origins,id',
        ]);

        DB::beginTransaction();
        try {
            // Crear el contacto
            $contact = Contact::create([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'] ?? null,
                'cellphone' => $validated['cellphone'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'email' => $validated['email'],
                'contact_status_id' => $validated['contact_status_id'],
                'disqualification_reason_id' => $validated['disqualification_reason_id'] ?? null,
                'owner_id' => $validated['owner_id'] ?? null,
                'occupation' => $validated['occupation'] ?? null,
                'job_position' => $validated['job_position'] ?? null,
                'birthdate' => $validated['birthdate'] ?? null,
                'address' => $validated['address'] ?? null,
                'country' => $validated['country'] ?? null,
            ]);

            // Guardar relaciones
            if (!empty($validated['deals'])) {
                $contact->deals()->attach($validated['deals']);
            }

            if (!empty($validated['projects'])) {
                $contact->projects()->attach($validated['projects']);
            }

            if (!empty($validated['campaigns'])) {
                // Marcar la primera campaña como original y la última como is_last
                $contact->campaigns()->attach(collect($validated['campaigns'])->mapWithKeys(function ($id, $index) use ($validated) {
                    return [
                        $id => [
                            'is_original' => $index === 0,
                            'is_last' => $index === array_key_last($validated['campaigns']),
                        ]
                    ];
                })->toArray());
            }

            if (!empty($validated['origins'])) {
                // Igual que con campañas: original y última
                $contact->origins()->attach(collect($validated['origins'])->mapWithKeys(function ($id, $index) use ($validated) {
                    return [
                        $id => [
                            'is_original' => $index === 0,
                            'is_last' => $index === array_key_last($validated['origins']),
                        ]
                    ];
                })->toArray());
            }

            // Log
            $this->logAction('create_contact', 'Contact', $contact->id, $contact->toArray());

            DB::commit();

            return response()->json([
                'message' => 'Contacto creado correctamente',
                'contact' => $contact->load(['status', 'owner', 'deals', 'projects', 'campaigns', 'origins'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al crear contacto', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Ver un contacto con sus relaciones.
     */
    public function show(Contact $contact)
    {
        $contact->load(['status', 'disqualificationReason', 'owner', 'deals', 'campaigns', 'origins', 'projects']);
        return new ContactResource($contact);
    }

    /**
     * Actualizar un contacto.
     */
    public function update(Request $request, Contact $contact)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'cellphone' => 'nullable|string|max:50',
            'phone' => 'nullable|string|max:50',
            'email' => "required|email|unique:crm_contacts,email,{$contact->id}",
            'contact_status_id' => 'required|exists:crm_contact_statuses,id',
            'disqualification_reason_id' => 'nullable|exists:crm_disqualification_reasons,id',
            'owner_id' => 'nullable|exists:users,id',
            'occupation' => 'nullable|string|max:255',
            'job_position' => 'nullable|string|max:255',
            'birthdate' => 'nullable|date',
            'address' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'deals' => 'array',
            'campaigns' => 'array',
            'origins' => 'array',
            'projects' => 'array',
        ]);

        if (!empty($validated['birthdate'])) {
            $validated['birthdate'] = \Carbon\Carbon::parse($validated['birthdate'])->format('Y-m-d');
        }

        DB::beginTransaction();
        try {
            $oldData = $contact->toArray();

            // Actualizar solo campos del modelo principal
            $contact->update($validated);

            // Sincronizar relaciones
            $contact->deals()->sync($validated['deals'] ?? []);
            $contact->campaigns()->sync($validated['campaigns'] ?? []);
            $contact->origins()->sync($validated['origins'] ?? []);
            $contact->projects()->sync($validated['projects'] ?? []);

            // Capturar solo cambios reales en los campos del contacto
            $changedFields = [];
            foreach ($contact->getChanges() as $field => $newValue) {
                // Omitimos timestamps para evitar ruido en los logs
                if (in_array($field, ['updated_at', 'created_at'])) {
                    continue;
                }
                $changedFields[$field] = [
                    'old' => $oldData[$field] ?? null,
                    'new' => $newValue
                ];
            }

            // Si también quieres registrar cambios en relaciones
            $relationChanges = [];
            if (isset($validated['deals'])) {
                $relationChanges['deals'] = $validated['deals'];
            }
            if (isset($validated['campaigns'])) {
                $relationChanges['campaigns'] = $validated['campaigns'];
            }
            if (isset($validated['origins'])) {
                $relationChanges['origins'] = $validated['origins'];
            }
            if (isset($validated['projects'])) {
                $relationChanges['projects'] = $validated['projects'];
            }

            $logData = [
                'fields' => $changedFields,
                'relations' => $relationChanges
            ];

            // Registrar log solo si hay cambios
            if (!empty($changedFields) || !empty($relationChanges)) {
                $this->logAction('update_contact', 'Contact', $contact->id, $logData);
            }

            DB::commit();

            return response()->json(['message' => 'Contacto actualizado correctamente', 'contact' => $contact]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al actualizar contacto', 'error' => $e->getMessage()], 500);
        }
    }

    public function updatePatch(Request $request, Contact $contact): JsonResponse
    {
        // ✅ Validación previa (opcional, pero mejora UX)
        if ($request->has('email') && $request->email !== $contact->email) {
            $exists = Contact::where('email', $request->email)
                ->where('id', '<>', $contact->id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'message' => 'El correo electrónico ya está asociado a otro contacto.'
                ], 422);
            }
        }

        if ($request->filled('birthdate')) {
            $request->merge([
                'birthdate' => \Carbon\Carbon::parse($request->input('birthdate'))->format('Y-m-d')
            ]);
        }

        try {
            $contact->fill($request->only([
                'first_name',
                'last_name',
                'phone',
                'cellphone',
                'email',
                'occupation',
                'job_position',
                'current_company',
                'birthdate',
                'contact_status_id',
                'lead_status',
                'owner_id',
                'interest_project',
                'source',
                'disqualification_reason_id',
                'address',
                'country',
            ]));

            $contact->save();
            
            $contact->load(['status', 'disqualificationReason', 'owner', 'deals', 'campaigns', 'origins', 'projects']);

            return (new ContactResource($contact))
                ->response()
                ->setStatusCode(200);

        } catch (QueryException $e) {
            if ($e->getCode() === '23000') {
                return response()->json([
                    'message' => 'El correo electrónico ya está asociado a otro contacto.'
                ], 422);
            }

            return response()->json([
                'message' => 'Error al actualizar el contacto',
                'error' => $e->getMessage()
            ], 500);
        }
    }

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