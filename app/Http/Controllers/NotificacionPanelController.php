<?php

namespace App\Http\Controllers;

use App\Models\NotificacionPanel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificacionPanelController extends Controller
{
    /**
     * Obtener lista de notificaciones recientes
     */
    public function index(Request $request)
    {
        $limit = $request->input('limit', 50);
        
        $notificaciones = NotificacionPanel::orderBy('created_at', 'desc')
            ->take($limit)
            ->get();

        $unreadCount = NotificacionPanel::where('read', false)->count();

        return response()->json([
            'data' => $notificaciones,
            'unread_count' => $unreadCount
        ]);
    }

    /**
     * Guardar una nueva notificación
     */
    public function store(Request $request)
    {
        $request->validate([
            'titulo' => 'required|string|max:255',
            'mensaje' => 'required|string',
            'tipo' => 'required|in:success,info,warning,danger',
            'entity_type' => 'nullable|string',
            'entity_id' => 'nullable',
            'admin_name' => 'nullable|string'
        ]);

        $adminId = null;
        if (Auth::guard('admin-sanctum')->check()) {
            $adminId = Auth::guard('admin-sanctum')->id();
        }

        $notificacion = NotificacionPanel::create([
            'titulo' => $request->titulo,
            'mensaje' => $request->mensaje,
            'tipo' => $request->tipo,
            'entity_type' => $request->entity_type,
            'entity_id' => $request->entity_id,
            'admin_id' => $adminId,
            'admin_name' => $request->admin_name ?? 'Sistema',
            'read' => false
        ]);

        return response()->json($notificacion, 201);
    }

    /**
     * Marcar una notificación como leída
     */
    public function markAsRead($id)
    {
        $notificacion = NotificacionPanel::find($id);
        
        if (!$notificacion) {
            return response()->json(['message' => 'Notificación no encontrada'], 404);
        }

        $notificacion->read = true;
        $notificacion->save();

        return response()->json(['success' => true]);
    }

    /**
     * Marcar todas como leídas
     */
    public function markAllAsRead()
    {
        NotificacionPanel::where('read', false)->update(['read' => true]);
        return response()->json(['success' => true]);
    }

    /**
     * Eliminar una notificación
     */
    public function destroy($id)
    {
        $notificacion = NotificacionPanel::find($id);
        
        if (!$notificacion) {
            return response()->json(['message' => 'Notificación no encontrada'], 404);
        }

        $notificacion->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Eliminar todas las notificaciones
     */
    public function destroyAll()
    {
        NotificacionPanel::truncate();
        return response()->json(['success' => true]);
    }
}
