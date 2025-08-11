<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        // Devuelve las últimas 10 notificaciones no leídas
        $notifications = $request->user()->unreadNotifications()->latest()->take(10)->get();
        return response()->json($notifications);
    }

    public function markAsRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();
        return response()->json(['message' => 'Notificaciones marcadas como leídas.']);
    }

    public function markOneAsRead(Request $request, DatabaseNotification $notification)
    {
        // Verificación de seguridad: Asegurarse de que la notificación pertenezca al usuario autenticado.
        if ($notification->notifiable_id !== $request->user()->id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $notification->markAsRead();
        
        return response()->json(['message' => 'Notificación marcada como leída.']);
    }
}