<?php

namespace App\Http\Controllers;

use App\Models\IntegranteProyecto;
use App\Models\Proyecto;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class InformeController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('rol:ADMIN', only: ['rango', 'usuario']),
            new Middleware('rol:ADMIN,TUTOR,JURADO', only: ['pendientes']),
        ];
    }

    // RF6: proyectos radicados entre dos fechas
    public function rango(Request $request)
    {
        $proyectos = null;

        if ($request->hasAny(['fecha_inicial', 'fecha_final'])) {
            $datos = $request->validate([
                'fecha_inicial' => ['required', 'date'],
                'fecha_final' => ['required', 'date', 'after_or_equal:fecha_inicial'],
            ], [
                'required' => 'El campo :attribute es obligatorio.',
                'date' => 'El campo :attribute no es una fecha válida.',
                'fecha_final.after_or_equal' => 'La fecha final no puede ser anterior a la fecha inicial.',
            ], [
                'fecha_inicial' => 'fecha inicial',
                'fecha_final' => 'fecha final',
            ]);

            $proyectos = Proyecto::whereDate('fecha_radicacion', '>=', $datos['fecha_inicial'])
                ->whereDate('fecha_radicacion', '<=', $datos['fecha_final'])
                ->orderBy('fecha_radicacion')
                ->orderBy('codigo_radicado')
                ->get();
        }

        return view('informes.rango', compact('proyectos'));
    }

    // RF7: proyectos con observaciones en estado PENDIENTE
    public function pendientes(Request $request)
    {
        $proyectos = Proyecto::visiblesPara($request->user())
            ->whereHas('correcciones', fn ($q) => $q->where('estado', 'PENDIENTE'))
            ->with(['correcciones' => fn ($q) => $q->where('estado', 'PENDIENTE')->orderBy('fecha_limite')])
            ->get()
            ->sortBy(fn ($p) => $p->correcciones->first()->fecha_limite->timestamp)
            ->values();

        return view('informes.pendientes', compact('proyectos'));
    }

    // RF8: proyectos de un docente o estudiante elegido en una lista desplegable
    public function usuario(Request $request)
    {
        $usuarios = User::where('rol', '!=', 'ADMIN')->orderBy('name')->get();
        $seleccionado = null;
        $participaciones = null;

        if ($request->has('user_id')) {
            $datos = $request->validate([
                'user_id' => ['required', 'exists:users,id'],
            ], [
                'user_id.required' => 'Selecciona un usuario.',
                'user_id.exists' => 'El usuario seleccionado no existe.',
            ]);

            $seleccionado = User::findOrFail($datos['user_id']);
            $participaciones = IntegranteProyecto::with('proyecto')
                ->where('user_id', $seleccionado->id)
                ->get()
                ->sortBy('proyecto.codigo_radicado')
                ->values();
        }

        return view('informes.usuario', compact('usuarios', 'seleccionado', 'participaciones'));
    }
}
