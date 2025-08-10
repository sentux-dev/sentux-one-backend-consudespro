<?php

namespace App\Http\Controllers\Api\CRM;

use App\Http\Controllers\Controller;
use App\Models\Crm\Company;
use App\Models\Crm\Contact;
use App\Models\Crm\ContactAssociation;
use App\Models\Crm\Deal; // Importar el modelo Deal
use App\Models\Crm\DealAssociation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class ContactAssociationController extends Controller
{
    public function index(Contact $contact)
    {

        // Log de deals->associations
        Log::info('ContactAssociationController@index', [
            'contact_id' => $contact->id,
            'associations_count' => $contact->associations->count(),
            'companies_count' => $contact->companies->count(),
            'deals_count' => $contact->deals->count(),
        ]);

        // Cargar las relaciones necesarias
        $contact->load(['companies', 'deals.dealAssociations.associable', 'associations.associatedContact']);

        return response()->json([
            'contacts' => $contact->associations->map(function ($assoc) {
                return [
                    'id' => $assoc->associatedContact->id,
                    'name' => $assoc->associatedContact->first_name . ' ' . $assoc->associatedContact->last_name,
                    'association_id' => $assoc->id,
                ];
            }),
            'companies' => $contact->companies->map(function ($company) {
                return [
                    'id' => $company->id,
                    'name' => $company->name,
                    'association_id' => $company->pivot->id, // El ID de la tabla pivote
                ];
            }),
            'deals' => $contact->deals->map(function ($deal) {
                 return [
                    'id' => $deal->id,
                    'name' => $deal->name,
                    'association_id' => $deal->dealAssociations->first()->id ?? null,
                ];
            }),
        ]);
    }

    public function store(Request $request, Contact $contact)
    {
        $validatedData = $request->validate([
            'association_type' => ['required', 'string', Rule::in(['contacts', 'companies', 'deals'])],
            'associated_id' => 'required|integer',
        ]);

        $type = $validatedData['association_type'];
        $associatedId = $validatedData['associated_id'];

        switch ($type) {
            case 'contacts':
                $association = ContactAssociation::create([
                    'contact_id' => $contact->id,
                    'associated_contact_id' => $associatedId,
                    'association_type' => 'contacts',
                ]);
                return response()->json(['message' => 'Asociación creada.', 'association' => $association], 201);

            case 'companies':
                if (!$company = Company::find($associatedId)) {
                    return response()->json(['message' => 'Empresa no encontrada.'], 404);
                }
                // attach() es la forma correcta de añadir a una relación muchos-a-muchos
                $contact->companies()->attach($company->id);
                return response()->json(['message' => 'Asociación con empresa creada.'], 201);

            case 'deals':
                if (!$deal = Deal::find($associatedId)) {
                    return response()->json(['message' => 'Negocio no encontrado.'], 404);
                }
                
                // Usamos el método `associate` que ya existe en tu modelo Deal,
                // que se encarga de crear el registro en la tabla `crm_deal_associations`.
                $deal->associate($contact, $validatedData['relation_type']);
                
                return response()->json(['message' => 'Asociación con negocio creada.'], 201);
        }
        return response()->json(['message' => 'Tipo de asociación no válido.'], 400);
    }


    public function update(Request $request, Contact $contact, ContactAssociation $association)
    {
        $validatedData = $request->validate([
            'relation_type' => 'nullable|string|max:255',
        ]);

        // Asegúrate de que la asociación pertenezca al contacto
        if ($association->contact_id !== $contact->id) {
            return response()->json(['message' => 'Association not found for this contact.'], 404);
        }

        $association->update($validatedData);

        return response()->json(['message' => 'Association updated successfully.', 'association' => $association]);
    }


    public function destroy(Contact $contact, string $type, int $associationId)
    {
        $deleted = false;

        switch ($type) {
            case 'contacts':
                // Para contacto-a-contacto, el associationId es el ID de la tabla crm_contact_associations
                $association = ContactAssociation::where('id', $associationId)
                                                 ->where('contact_id', $contact->id)
                                                 ->first();
                if ($association) {
                    $association->delete();
                    $deleted = true;
                }
                break;
            
            case 'companies':
                // Para empresas, el associationId es el ID de la EMPRESA.
                // Usamos detach() en la relación para eliminar el registro de la tabla pivote.
                if ($contact->companies()->detach($associationId)) {
                    $deleted = true;
                }
                break;

            case 'deals':
                 // Para negocios, el associationId es el ID del NEGOCIO.
                $dealAssociation = DealAssociation::where('associable_id', $contact->id)
                                                  ->where('associable_type', Contact::class)
                                                  ->where('deal_id', $associationId)
                                                  ->first();
                if ($dealAssociation) {
                    $dealAssociation->delete();
                    $deleted = true;
                }
                break;
        }

        if ($deleted) {
            return response()->json(['message' => 'Asociación eliminada correctamente.']);
        }

        return response()->json(['message' => 'Asociación no encontrada o no pertenece a este contacto.'], 404);
    }
}