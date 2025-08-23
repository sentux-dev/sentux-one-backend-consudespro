<?php

namespace App\Http\Controllers\Api\CRM;

use App\Http\Controllers\Controller;
use App\Http\Resources\CRM\ContactCollection;
use App\Http\Resources\CRM\ContactResource;
use Illuminate\Http\Request;
use App\Models\Crm\Contact;
use App\Models\Crm\Campaign;
use App\Models\Crm\ContactEntryHistory;
use App\Models\Crm\ContactSequenceEnrollment;
use App\Models\Crm\Origin;
use App\Models\Crm\Deal;
use App\Models\Crm\Sequence;
use App\Models\RealState\Project;
use App\Models\Log;
use App\Policies\ContactPolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Log as FacadesLog;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

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

        // --- 1. Subconsulta para obtener la fecha del último ingreso de cada contacto ---
        $latestEntrySubquery = DB::table('crm_contact_entry_history')
            ->select('contact_id', DB::raw('MAX(entry_at) as last_entry_at'))
            ->groupBy('contact_id');

        // --- 2. Unimos la subconsulta a la consulta principal de contactos ---
        $query->leftJoinSub($latestEntrySubquery, 'latest_entry', function ($join) {
            $join->on('crm_contacts.id', '=', 'latest_entry.contact_id');
        });
        
        // --- 3. Seleccionamos todas las columnas de contacto más nuestro nuevo campo ---
        $query->select('crm_contacts.*', 'latest_entry.last_entry_at');

        // La carga de relaciones (with) se mantiene igual
        $query->with([
            'status', 'disqualificationReason', 'owner', 'deals:id,name',
            'campaigns:id,name', 'origins:id,name', 'projects:id,name'
        ])->withCount(['deals', 'projects', 'campaigns', 'origins']);

        // --- El resto de los filtros se mantienen exactamente igual ---
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function (Builder $q) use ($search) {
                $q->where('first_name', 'like', "%$search%")
                  ->orWhere('last_name', 'like', "%$search%")
                  ->orWhere('email', 'like', "%$search%")
                  ->orWhere('phone', 'like', "%$search%");
            });
        }

        if ($request->filled('status_id')) $query->where('contact_status_id', $request->status_id);
        if ($request->filled('owner_id')) $query->whereIn('owner_id', is_array($request->owner_id) ? $request->owner_id : [$request->owner_id]);
        if ($request->filled('loss_reason_id')) $query->where('disqualification_reason_id', $request->loss_reason_id);
        
        if ($request->filled('project_id')) $query->whereHas('projects', fn($q) => $q->where('real_state_projects.id', $request->project_id));
        if ($request->filled('campaign_id')) {
            $query->whereHas('campaigns', function ($q) use ($request) {
                // Ya no es necesario especificar la tabla aquí, Eloquent lo sabe
                $q->where('id', $request->campaign_id);
            });
        }
        if ($request->filled('origin_id')) {
            $query->whereHas('origins', function ($q) use ($request) {
                $q->where('id', $request->origin_id);
            });
        }

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
            // Este filtro ahora puede ser más potente si lo ajustas para que también
            // considere la fecha de último ingreso en lugar de solo created_at.
            $query->whereBetween(DB::raw('COALESCE(latest_entry.last_entry_at, crm_contacts.created_at)'), [$request->start_date, $request->end_date]);
        }
        
        // --- 4. Lógica de Ordenamiento Corregida ---
        $sortField = $request->get('sort_field', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        
        if ($sortField === 'created_at') {
            // ¡LA CLAVE! Ordenamos por la fecha de último ingreso. Si es nula, usamos la de creación.
            $query->orderBy(DB::raw('COALESCE(latest_entry.last_entry_at, crm_contacts.created_at)'), $sortOrder);
        } elseif ($sortField === 'full_name') {
            $query->orderBy('first_name', $sortOrder)->orderBy('last_name', $sortOrder);
        } else {
            // Para otros campos, el ordenamiento es directo sobre la tabla de contactos
            $query->orderBy('crm_contacts.' . $sortField, $sortOrder);
        }

        $contacts = $query->paginate($request->get('per_page', 10));
        
        return new ContactCollection($contacts);
    }

    /**
     * Crear un nuevo contacto.
     */
    public function store(Request $request)
    {
        // Normaliza espacios (opcional pero útil)
        $request->merge([
            'first_name' => trim((string) $request->input('first_name')),
            'last_name'  => trim((string) $request->input('last_name')),
            'email'      => trim((string) $request->input('email')),
            'phone'  => trim((string) $request->input('phone')),
        ]);

        $this->nullifyEmpty($request, ['email','phone']);

        $validated = $request->validate([
            'first_name' => ['required','string','max:255'],
            'last_name'  => ['nullable','string','max:255'],

            // 👇 Al menos uno requerido: email O phone
            'email' => [
                'required_without:phone',
                'nullable',
                'email',
                Rule::unique('crm_contacts', 'email')->whereNull('deleted_at'),
            ],
            'phone' => [
                'required_without:email',
                'nullable',
                'string',
                'max:50',
                Rule::unique('crm_contacts', 'phone')->whereNull('deleted_at'),
            ],
            'contact_status_id' => ['required','exists:crm_contact_statuses,id'],
            'owner_id'          => ['nullable','exists:users,id'],

            'projects'   => ['nullable','array'],

            // Singular
            'campaign_id' => ['nullable','integer','exists:crm_campaigns,id'],
            'origin_id'   => ['nullable','integer','exists:crm_origins,id'],

            'custom_fields'              => ['nullable','array'],
            'custom_fields.*.field_id'   => ['required','exists:crm_contact_custom_fields,id'],
            'custom_fields.*.value'      => ['nullable','string','max:65535'],
        ]);

        DB::beginTransaction();
        try {
            // Crea solo con campos del modelo (evita keys ajenas)
            $contact = Contact::create([
                'first_name'            => $validated['first_name'],
                'last_name'             => $validated['last_name'] ?? null,
                'email'                 => $validated['email'] ?? null,
                'phone'                 => $validated['phone'] ?? null,
                'contact_status_id'     => $validated['contact_status_id'],
                'owner_id'              => $validated['owner_id'] ?? Auth::id(),
                'occupation'            => $request->input('occupation'),      // si los usas
                'job_position'          => $request->input('job_position'),
                'current_company'       => $request->input('current_company'),
                'birthdate'             => $request->input('birthdate'),
                'address'               => $request->input('address'),
                'country'               => $request->input('country'),
                'active'                => $request->boolean('active', true),
                'subscribed_to_newsletter'      => $request->boolean('subscribed_to_newsletter'),
                'subscribed_to_product_updates' => $request->boolean('subscribed_to_product_updates'),
                'subscribed_to_promotions'      => $request->boolean('subscribed_to_promotions'),
            ]);

            // Campos personalizados
            if (!empty($validated['custom_fields'])) {
                foreach ($validated['custom_fields'] as $cf) {
                    if ($cf['value'] !== null && $cf['value'] !== '') {
                        $contact->customFieldValues()->create([
                            'custom_field_id' => $cf['field_id'],
                            'value'           => $cf['value'],
                        ]);
                    }
                }
            }

            // Proyectos
            if (!empty($validated['projects'])) {
                $contact->projects()->attach($validated['projects']);
            }

            // Campaña / Origen iniciales (historial)
            if (!empty($validated['campaign_id'])) {
                $contact->campaigns()->attach($validated['campaign_id'], [
                    'is_original' => true,
                    'is_last'     => true,
                    'entry_at'    => now(),
                ]);
            }
            if (!empty($validated['origin_id'])) {
                $contact->origins()->attach($validated['origin_id'], [
                    'is_original' => true,
                    'is_last'     => true,
                    'entry_at'    => now(),
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Contacto creado correctamente',
                'contact' => new ContactResource($contact),
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al crear contacto',
                'error'   => $e->getMessage(),
            ], 500);
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
    public function update(Request $request, Contact $contact): JsonResponse
    {
        // Normaliza espacios
        $request->merge([
            'first_name' => trim((string) $request->input('first_name', $contact->first_name)),
            'last_name'  => trim((string) $request->input('last_name', $contact->last_name)),
            'email'      => trim((string) $request->input('email', $contact->email)),
            'phone'      => trim((string) $request->input('phone', $contact->phone)),
        ]);

        $this->nullifyEmpty($request, ['email','phone']);

        // Reglas: en PUT pedimos first_name y contact_status_id; email/phone con unicidad e interdependencia
        $validator = Validator::make($request->all(), [
            'first_name' => ['required','string','max:255'],
            'last_name'  => ['nullable','string','max:255'],

            'email' => [
                'nullable','email',
                Rule::unique('crm_contacts', 'email')
                    ->ignore($contact->id)
                    ->whereNull('deleted_at'),
            ],
            'phone' => [
                'nullable','string','max:50',
                Rule::unique('crm_contacts', 'phone')
                    ->ignore($contact->id)
                    ->whereNull('deleted_at'),
            ],
            'contact_status_id' => ['required','exists:crm_contact_statuses,id'],
            'owner_id'          => ['nullable','exists:users,id'],

            'projects'   => ['sometimes','array'],

            // asociaciones (si aplican en tu UI para PUT)
            'campaigns'  => ['sometimes','array'],
            'origins'    => ['sometimes','array'],

            // custom fields (si los soportas en update)
            'custom_fields'            => ['sometimes','array'],
            'custom_fields.*.field_id' => ['required_with:custom_fields','exists:crm_contact_custom_fields,id'],
            'custom_fields.*.value'    => ['nullable','string','max:65535'],
        ]);

        // Regla compuesta: al final, debe quedar al menos uno (email o phone)
        $validator->after(function ($v) use ($request, $contact) {
            $finalEmail = $request->filled('email') ? $request->input('email') : $contact->email;
            $finalCell  = $request->filled('phone') ? $request->input('phone') : $contact->phone;

            // Si explícitamente envías null para uno de ellos, respeta eso:
            if ($request->has('email') && $request->input('email') === null) {
                $finalEmail = null;
            }
            if ($request->has('phone') && $request->input('phone') === null) {
                $finalCell = null;
            }

            if (empty($finalEmail) && empty($finalCell)) {
                $v->errors()->add('email', 'Debes proporcionar al menos el correo o el número de celular.');
                $v->errors()->add('phone', 'Debes proporcionar al menos el correo o el número de celular.');
            }
        });

        $validated = $validator->validate();

        DB::beginTransaction();
        try {
            // Actualiza campos del modelo
            $contact->update([
                'first_name'            => $validated['first_name'],
                'last_name'             => $validated['last_name'] ?? null,
                'email'                 => $validated['email'] ?? ($request->has('email') ? null : $contact->email),
                'phone'                 => $validated['phone'] ?? ($request->has('phone') ? null : $contact->phone),
                'contact_status_id'     => $validated['contact_status_id'],
                'owner_id'              => $validated['owner_id'] ?? $contact->owner_id,
                'occupation'            => $request->input('occupation', $contact->occupation),
                'job_position'          => $request->input('job_position', $contact->job_position),
                'current_company'       => $request->input('current_company', $contact->current_company),
                'birthdate'             => $request->input('birthdate', $contact->birthdate),
                'address'               => $request->input('address', $contact->address),
                'country'               => $request->input('country', $contact->country),
                'active'                => $request->has('active') ? $request->boolean('active') : $contact->active,
                'subscribed_to_newsletter'      => $request->has('subscribed_to_newsletter') ? $request->boolean('subscribed_to_newsletter') : $contact->subscribed_to_newsletter,
                'subscribed_to_product_updates' => $request->has('subscribed_to_product_updates') ? $request->boolean('subscribed_to_product_updates') : $contact->subscribed_to_product_updates,
                'subscribed_to_promotions'      => $request->has('subscribed_to_promotions') ? $request->boolean('subscribed_to_promotions') : $contact->subscribed_to_promotions,
            ]);

            // Custom fields (opcional)
            if ($request->has('custom_fields')) {
                $contact->customFieldValues()->delete();
                foreach ($validated['custom_fields'] ?? [] as $cf) {
                    if ($cf['value'] !== null && $cf['value'] !== '') {
                        $contact->customFieldValues()->create([
                            'custom_field_id' => $cf['field_id'],
                            'value'           => $cf['value'],
                        ]);
                    }
                }
            }

            // Proyectos
            if ($request->has('projects')) {
                $contact->projects()->sync($validated['projects'] ?? []);
            }

            // Historial campañas / orígenes
            if ($request->has('campaigns')) {
                $this->updateAssociationHistory($contact, 'campaigns', $validated['campaigns'] ?? []);
            }
            if ($request->has('origins')) {
                $this->updateAssociationHistory($contact, 'origins', $validated['origins'] ?? []);
            }

            DB::commit();

            $contact->load(['status','disqualificationReason','owner','deals','campaigns','origins','projects']);
            return (new ContactResource($contact))->response()->setStatusCode(200);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al actualizar contacto', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Actualiza campos específicos de un contacto (PATCH).
     */
    public function updatePatch(Request $request, Contact $contact): JsonResponse
    {
        // Normaliza espacios (sin pisar si no vienen)
        if ($request->has('first_name')) $request->merge(['first_name' => trim((string) $request->input('first_name'))]);
        if ($request->has('last_name'))  $request->merge(['last_name'  => trim((string) $request->input('last_name'))]);
        if ($request->has('email'))      $request->merge(['email'      => trim((string) $request->input('email'))]);
        if ($request->has('phone'))      $request->merge(['phone'      => trim((string) $request->input('phone'))]);

        $this->nullifyEmpty($request, ['email','phone']);

        $validator = Validator::make($request->all(), [
            'first_name' => ['sometimes','string','max:255'],
            'last_name'  => ['sometimes','string','max:255'],

            'email' => [
                'sometimes','nullable','email',
                Rule::unique('crm_contacts', 'email')
                    ->ignore($contact->id)
                    ->whereNull('deleted_at'),
            ],
            'phone' => [
                'sometimes','nullable','string','max:50',
                Rule::unique('crm_contacts', 'phone')
                    ->ignore($contact->id)
                    ->whereNull('deleted_at'),
            ],

            'contact_status_id' => ['sometimes','exists:crm_contact_statuses,id'],
            'owner_id'          => ['sometimes','nullable','exists:users,id'],

            'projects'   => ['sometimes','array'],
            'campaigns'  => ['sometimes','array'],
            'origins'    => ['sometimes','array'],

            'custom_fields'            => ['sometimes','array'],
            'custom_fields.*.field_id' => ['required_with:custom_fields','exists:crm_contact_custom_fields,id'],
            'custom_fields.*.value'    => ['nullable','string','max:65535'],
        ]);

        // Regla compuesta: tras aplicar parches, debe quedar al menos email o phone
        $validator->after(function ($v) use ($request, $contact) {
            $finalEmail = $request->has('email') ? $request->input('email') : $contact->email;
            $finalCell  = $request->has('phone') ? $request->input('phone') : $contact->phone;

            if ($finalEmail === '') $finalEmail = null;
            if ($finalCell === '')  $finalCell  = null;

            if (empty($finalEmail) && empty($finalCell)) {
                $v->errors()->add('email', 'Debes conservar al menos el correo o el número de celular.');
                $v->errors()->add('phone', 'Debes conservar al menos el correo o el número de celular.');
            }
        });

        $validated = $validator->validate();

        try {
            DB::beginTransaction();

            // Solo campos presentes
            $contact->fill($request->only([
                'first_name','last_name','phone','phone','email','occupation',
                'job_position','birthdate','contact_status_id','owner_id',
                'disqualification_reason_id','address','country',
                'subscribed_to_newsletter','subscribed_to_product_updates','subscribed_to_promotions',
            ]));

            // Custom fields
            if ($request->has('custom_fields')) {
                $contact->customFieldValues()->delete();
                foreach ($validated['custom_fields'] ?? [] as $cf) {
                    if ($cf['value'] !== null && $cf['value'] !== '') {
                        $contact->customFieldValues()->create([
                            'custom_field_id' => $cf['field_id'],
                            'value'           => $cf['value'],
                        ]);
                    }
                }
            }

            // Proyectos
            if ($request->has('projects')) {
                $contact->projects()->sync($validated['projects'] ?? []);
            }

            // Historial
            if ($request->has('campaigns')) {
                $this->updateAssociationHistory($contact, 'campaigns', $validated['campaigns'] ?? []);
            }
            if ($request->has('origins')) {
                $this->updateAssociationHistory($contact, 'origins', $validated['origins'] ?? []);
            }

            $contact->save();
            DB::commit();

            $contact->load(['status','disqualificationReason','owner','deals','campaigns','origins','projects']);
            return (new ContactResource($contact))->response()->setStatusCode(200);

        } catch (\Throwable $e) {
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

        // ✅ Construimos la consulta sobre la tabla de historial correcta
        $query = ContactEntryHistory::where('contact_id', $contact->id)
            ->orderBy('entry_at', 'asc');

        if ($type === 'campaigns') {
            // Unimos con la tabla de campañas para obtener el nombre
            $query->join('crm_campaigns', 'crm_contact_entry_history.campaign_id', '=', 'crm_campaigns.id')
                ->whereNotNull('campaign_id')
                ->select('crm_campaigns.name', 'crm_contact_entry_history.*');
        } else { // origins
            // Unimos con la tabla de orígenes para obtener el nombre
            $query->join('crm_origins', 'crm_contact_entry_history.origin_id', '=', 'crm_origins.id')
                ->whereNotNull('origin_id')
                ->select('crm_origins.name', 'crm_contact_entry_history.*');
        }
        
        $history = $query->get();
            
        $formattedHistory = $history->map(function ($item) {
            return [
                'name' => $item->name,
                'assigned_at' => $item->entry_at->toDateTimeString(),
                'is_original' => (bool)$item->is_original,
                'is_last' => (bool)$item->is_last,
            ];
        });

        return response()->json($formattedHistory);
    }

    private function nullifyEmpty( Request $request, array $keys): void
    {
        $payload = $request->all();
        foreach ($keys as $key) {
            if (array_key_exists($key, $payload)) {
                $v = is_string($payload[$key]) ? trim($payload[$key]) : $payload[$key];
                $payload[$key] = ( $v === '' ? null : $v);
            }
        }
        $request->replace($payload);
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

    public function enrollInSequence(Request $request, Contact $contact)
    {
        $validated = $request->validate(['sequence_id' => 'required|integer|exists:crm_sequences,id']);
        $sequenceId = $validated['sequence_id'];

        if ($contact->sequenceEnrollments()->where('sequence_id', $sequenceId)->where('status', 'active')->exists()) {
            return response()->json(['message' => 'El contacto ya está activo en esta secuencia.'], 409);
        }

        $firstStep = Sequence::find($sequenceId)->steps()->orderBy('order')->first();
        $nextStepDueAt = null;
        if ($firstStep) {
            $nextStepDueAt = now()->add($firstStep->delay_unit, $firstStep->delay_amount);
        }

        ContactSequenceEnrollment::create([
            'contact_id' => $contact->id,
            'sequence_id' => $sequenceId,
            'enrolled_at' => now(),
            'status' => 'active',
            'current_step' => 0,
            'next_step_due_at' => $nextStepDueAt,
        ]);
        
        return response()->json(['message' => 'Contacto inscrito en la secuencia con éxito.']);
    }

    public function getSequenceEnrollments(Contact $contact)
    {
        $enrollments = $contact->sequenceEnrollments()
            // Carga la relación 'sequence', pero solo selecciona las columnas 'id' y 'name'
            // para que la respuesta sea más ligera y eficiente.
            ->with('sequence:id,name') 
            ->where('status', 'active')
            ->get();
            
        return response()->json($enrollments);
    }

    public function stopSequenceEnrollment(Contact $contact, ContactSequenceEnrollment $enrollment)
    {
        // 1. Verificación de seguridad: Asegurarse de que la inscripción que se quiere
        //    detener realmente pertenece al contacto especificado en la URL.
        //    Esto previene que se pueda detener la secuencia de un contacto diferente por error.
        if ($enrollment->contact_id !== $contact->id) {
            return response()->json(['message' => 'Esta inscripción no pertenece al contacto especificado.'], 403); // 403 Forbidden
        }

        // 2. Actualizar el estado de la inscripción.
        //    Cambiamos el estado a 'stopped' y ponemos la fecha del próximo paso en null
        //    para que el comando programado la ignore por completo.
        $enrollment->update([
            'status' => 'stopped',
            'next_step_due_at' => null
        ]);

        // 3. Devolver una respuesta de éxito.
        return response()->json(['message' => 'La secuencia ha sido detenida para este contacto.']);
    }

}