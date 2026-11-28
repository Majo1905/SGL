<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

// notificaciones internas del usuario autenticado (la campana del encabezado)
class NotificacionController extends Controller
{
    public function index(Request $request)
    {
        return view('notificaciones.index', [
            'notificaciones' => $request->user()->notifications()->latest()->limit(100)->get(),
        ]);
    }

    // lo consulta la campana cada 30 segundos para mostrar avisos sin recargar la página
    public function contador(Request $request)
    {
        $usuario = $request->user();

        return response()->json([
            'no_leidas' => $usuario->unreadNotifications()->count(),
            'ultima' => optional($usuario->unreadNotifications()->latest()->first())->data['titulo'] ?? null,
        ]);
    }

    // marca la notificación como leída y lleva al usuario a lo que ocurrió
    public function abrir(Request $request, string $id)
    {
        $notificacion = $request->user()->notifications()->findOrFail($id);
        $notificacion->markAsRead();

        return redirect($notificacion->data['url'] ?? route('notificaciones.index'));
    }

    public function leerTodas(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return redirect()->route('notificaciones.index')->with('mensaje', 'Marcaste todas las notificaciones como leídas.');
    }
}
