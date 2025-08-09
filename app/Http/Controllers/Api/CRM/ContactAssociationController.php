<?php

namespace App\Http\Controllers\Api\CRM;

use App\Http\Controllers\Controller;
use App\Models\Crm\Company;
use App\Models\Crm\Contact;
use App\Models\Crm\ContactAssociation;
use App\Models\Crm\Deal; // Importar el modelo Deal
use App\Models\Crm\DealAssociation;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContactAssociationController extends Controller
{
    public function index(Contact $contact)
    {
        // Eager load only the 'associatedContact' relationship, as ContactAssociation
        // primarily handles Contact-to-Contact links.
        $contactAssociations = $contact->associations()->with('associatedContact')->get();

        // Also, load the polymorphic associations where THIS contact is associated with other models
        // (e.g., Deals that this Contact is associated with via crm_deal_associations).
        // This is where you'd retrieve deals, companies, tickets, orders that are linked TO THIS CONTACT.
        $dealAssociations = $contact->dealAssociations()->with('deal')->get(); // 'deal' is the inverse of the polymorphic relation in DealAssociation

        // You might need to add similar relationships for companies, tickets, orders
        // if they also have `morphMany` back to Contact.
        // For example, if a Company has morphMany associations and one of them is a Contact:
        // $companyAssociations = $contact->companyAssociations()->with('company')->get(); // You'd need a 'companyAssociations' relation on Contact model

        $formattedAssociations = [
            'contacts' => [],
            'companies' => [],
            'deals' => [],
            'tickets' => [],
            'orders' => [],
        ];

        // Format Contact-to-Contact associations
        foreach ($contactAssociations as $association) {
            if ($association->associatedContact) {
                $formattedAssociations['contacts'][] = [
                    'id' => $association->associatedContact->id,
                    'name' => $association->associatedContact->first_name . ' ' . $association->associatedContact->last_name,
                    'relation_type' => $association->relation_type,
                    'association_id' => $association->id, // ID of the pivot table
                ];
            }
        }

        // Format Deal associations where this contact is linked to a deal
        foreach ($dealAssociations as $dealAssociation) {
            if ($dealAssociation->deal) { // Access the 'deal' relationship defined in DealAssociation model
                $formattedAssociations['deals'][] = [
                    'id' => $dealAssociation->deal->id,
                    'name' => $dealAssociation->deal->name,
                    'relation_type' => $dealAssociation->relation_type,
                    'association_id' => $dealAssociation->id, // ID of the deal_associations table record
                ];
            }
        }

        // Add similar loops for Companies, Tickets, Orders if they have polymorphic associations to Contact
        // and you retrieve them correctly (e.g., via $contact->companyAssociations).
        // You'll need to define those inverse polymorphic relations on your Contact model.

        return response()->json($formattedAssociations);
    }

    public function store(Request $request, Contact $contact)
    {
        $validatedData = $request->validate([
            'association_type' => ['required', 'string', Rule::in(['contacts', 'companies', 'deals', 'tickets', 'orders'])],
            'relation_type' => 'nullable|string|max:255',
            'associated_id' => 'required|integer',
        ]);

        $associatedModel = null;
        $associationTable = null;

        switch ($validatedData['association_type']) {
            case 'contacts':
                $associatedModel = Contact::find($request->associated_id); 
                $associationTable = Contact::class;
                break;
            case 'companies':
                $company = Company::find($validatedData['associated_id']);
                if (!$company) {
                    return response()->json(['message' => 'Empresa no encontrada.'], 404);
                }
                // Usamos syncWithoutDetaching para añadir sin duplicar
                $contact->companies()->syncWithoutDetaching([$company->id]);
                
                // Cargar la asociación recién creada para devolverla
                $newAssociation = $contact->companies()->where('company_id', $company->id)->first();
                
                return response()->json([
                    'message' => 'Asociación con empresa creada.',
                    'association' => $newAssociation // Devolvemos la nueva asociación
                ], 201);
                break;
            case 'deals':
                $associatedModel = Deal::find($request->associated_id);
                $associationTable = Deal::class; 
                break;
            case 'tickets':
                // Asume que tienes un modelo Ticket
                // $associatedModel = Ticket::find($validatedData['associated_id']);
                // $associationTable = Ticket::class;
                break;
            case 'orders':
                // Asume que tienes un modelo Order
                // $associatedModel = Order::find($validatedData['associated_id']);
                // $associationTable = Order::class;
                break;
        }

        if (!$associatedModel) {
            return response()->json(['message' => 'Associated entity not found.'], 404);
        }

        if ($validatedData['association_type'] === 'contacts') {
            $existingAssociation = ContactAssociation::where('contact_id', $contact->id)
                ->where('associated_contact_id', $associatedModel->id)
                ->first();

            if ($existingAssociation) {
                return response()->json(['message' => 'Association already exists.'], 409);
            }

            $association = ContactAssociation::create([
                'contact_id' => $contact->id,
                'associated_contact_id' => $associatedModel->id,
                'association_type' => $validatedData['association_type'],
                'relation_type' => $validatedData['relation_type'],
            ]);
            return response()->json(['message' => 'Association created successfully.', 'association' => $association], 201);
        }

        if ($associatedModel && $associatedModel instanceof Deal) { 
            $existingPolymorphicAssociation = $associatedModel->dealAssociations()
                ->where('associable_id', $contact->id)
                ->where('associable_type', Contact::class)
                ->first();

            if ($existingPolymorphicAssociation) {
                return response()->json(['message' => 'Polymorphic association already exists.'], 409);
            }

            $polymorphicAssociation = $associatedModel->associate(
                $contact,
                $validatedData['relation_type']
            );

            return response()->json(['message' => 'Polymorphic association created successfully.', 'association' => $polymorphicAssociation], 201);
        }

        // Para asociaciones polimórficas (Company, Ticket, Order)
        if ($associatedModel && $associationTable) {
            // Verificar si ya existe esta asociación polimórfica inversa
            // Esto es crucial para evitar duplicados en crm_deal_associations
            $existingPolymorphicAssociation = $associatedModel->associations() // Acceder a la relación de asociaciones del modelo asociado (ej. Deal)
                ->where('associable_id', $contact->id)
                ->where('associable_type', Contact::class)
                ->first();

            if ($existingPolymorphicAssociation) {
                return response()->json(['message' => 'Polymorphic association already exists.'], 409);
            }

            // Crear la asociación polimórfica en la tabla crm_deal_associations (o la correspondiente)
            $polymorphicAssociation = $associatedModel->associations()->create([
                'associable_id' => $contact->id,
                'associable_type' => Contact::class, // El tipo de modelo que se asocia al Deal
                'relation_type' => $validatedData['relation_type'],
            ]);

            return response()->json(['message' => 'Polymorphic association created successfully.', 'association' => $polymorphicAssociation], 201);
        }


        return response()->json(['message' => 'Invalid association type.'], 400);
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


    public function destroy(Contact $contact, $associationId)
    {
        // Intenta buscar en la tabla de asociaciones directas de contactos
        $association = ContactAssociation::find($associationId);
        if ($association && $association->contact_id === $contact->id) {
            $association->delete();
            return response()->json(['message' => 'Asociación de contacto eliminada.']);
        }

        // Si no la encontró, busca en la tabla de asociaciones de negocios
        $dealAssociation = DealAssociation::find($associationId);
        if ($dealAssociation && $dealAssociation->associable_id === $contact->id && $dealAssociation->associable_type === Contact::class) {
            $dealAssociation->delete();
            return response()->json(['message' => 'Asociación de negocio eliminada.']);
        }

        // Si se añaden más tipos de asociaciones (empresas, tickets), se añadirían búsquedas similares aquí

        return response()->json(['message' => 'Asociación no encontrada o no pertenece a este contacto.'], 404);
    }
}