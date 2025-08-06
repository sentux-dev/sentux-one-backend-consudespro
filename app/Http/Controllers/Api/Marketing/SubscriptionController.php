<?php
namespace App\Http\Controllers\Api\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Crm\Contact;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    /**
     * Obtiene la información de un contacto para la página de baja.
     */
    public function getContactForUnsubscribe(Contact $contact)
    {
        // Usamos Route Model Binding con el UUID
        return response()->json([
            'email' => $contact->email,
            'is_unsubscribed' => (bool)$contact->unsubscribed_at,
        ]);
    }
    
    /**
     * Procesa la cancelación de la suscripción.
     */
    public function processUnsubscribe(Contact $contact)
    {
        if (!$contact->unsubscribed_at) {
            $contact->unsubscribed_at = now();
            $contact->save();
        }
        
        return response()->json(['message' => 'Tu suscripción ha sido cancelada con éxito.']);
    }

    public function getContactForUpdateProfile(Contact $contact)
    {
        return response()->json([
            'email' => $contact->email,
            'preferences' => [
                'newsletter' => $contact->subscribed_to_newsletter,
                'product_updates' => $contact->subscribed_to_product_updates,
                'promotions' => $contact->subscribed_to_promotions,
            ]
        ]);
    }

    public function processProfileUpdate(Request $request, Contact $contact)
    {
        $validated = $request->validate([
            'preferences' => 'required|array',
            'preferences.newsletter' => 'required|boolean',
            'preferences.product_updates' => 'required|boolean',
            'preferences.promotions' => 'required|boolean',
        ]);
        
        $contact->update([
            'subscribed_to_newsletter' => $validated['preferences']['newsletter'],
            'subscribed_to_product_updates' => $validated['preferences']['product_updates'],
            'subscribed_to_promotions' => $validated['preferences']['promotions'],
        ]);
        
        // Si el usuario desmarca todo, también lo consideramos como desuscrito globalmente.
        if (!$contact->subscribed_to_newsletter && !$contact->subscribed_to_product_updates && !$contact->subscribed_to_promotions) {
            $contact->unsubscribed_at = now();
        } else {
            $contact->unsubscribed_at = null; // Si marca al menos una, se resuscribe.
        }
        $contact->save();

        return response()->json(['message' => 'Tus preferencias han sido actualizadas con éxito.']);
    }

}