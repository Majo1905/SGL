<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProyectoRequest;
use App\Models\Correccion;
use App\Models\Proyecto;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;

class ProyectoController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('rol:ADMIN,ESTUDIANTE', only: ['create', 'store']),
            new Middleware('rol:ADMIN', only: ['destroy']),
            new Middleware('rol:ADMIN,TUTOR,JURADO', only: ['cambiarEstado']),
        ];
    }

    public function index(Request $request)
    {
        $usuario = $request->user();

        $proyectos = Proyecto::visiblesPara($usuario)
            ->with(['usuarioCreador', 'correcciones'])
            ->latest()
            ->get();

        // observaciones pendientes que ya vencieron o vencen en los próximos 3 días
        $alertas = Correccion::with('proyecto')
            ->where('estado', 'PENDIENTE')
            ->whereDate('fecha_limite', '<=', today()->addDays(3))
            ->whereHas('proyecto', fn ($q) => $q->visiblesPara($usuario))
            ->orderBy('fecha_limite')
            ->get();

        return view('dashboard', compact('proyectos', 'alertas'));
    }

    public function create()
    {
        return view('proyectos.create', [
            'proyecto' => new Proyecto(),
            'lineas' => $this->lineasExistentes(),
        ]);
    }

    public function store(ProyectoRequest $request)
    {
        $usuario = $request->user();

        $datos = $request->validated();
        // solo el administrador puede fijar el estado; al radicar siempre inicia en revisión
        if (! $usuario->esAdmin() || empty($datos['estado'])) {
            unset($datos['estado']);
        }
        $datos['codigo_radicado'] = Proyecto::siguienteCodigo();
        $datos['usuario_creador_id'] = $usuario->id;

        $proyecto = Proyecto::create($datos);

        // si radica un estudiante, queda vinculado automáticamente como autor
        if ($usuario->esEstudiante()) {
            $proyecto->integrantes()->create([
                'user_id' => $usuario->id,
                'rol_en_proyecto' => 'AUTOR_ESTUDIANTE',
            ]);
        }

        return redirect()->route('proyectos.show', $proyecto)
            ->with('mensaje', "Proyecto {$proyecto->codigo_radicado} radicado correctamente.");
    }

    public function show(Proyecto $proyecto)
    {
        $this->autorizarProyecto($proyecto);

        $proyecto->load(['correcciones', 'integrantes.user']);

        // solo se ofrecen los usuarios que todavía no hacen parte del proyecto
        $usuarios = User::whereNotIn('id', $proyecto->integrantes->pluck('user_id'))
            ->where('rol', '!=', 'ADMIN')
            ->orderBy('name')
            ->get();

        return view('proyectos.show', compact('proyecto', 'usuarios'));
    }

    public function edit(Proyecto $proyecto)
    {
        $this->autorizarEdicion($proyecto);

        return view('proyectos.edit', [
            'proyecto' => $proyecto,
            'lineas' => $this->lineasExistentes(),
        ]);
    }

    public function update(ProyectoRequest $request, Proyecto $proyecto)
    {
        $this->autorizarEdicion($proyecto);

        $datos = $request->validated();
        // el estado solo lo cambia el administrador
        if (! $request->user()->esAdmin()) {
            unset($datos['estado']);
        }

        $proyecto->update($datos);

        return redirect()->route('proyectos.show', $proyecto)
            ->with('mensaje', 'Los datos del proyecto se actualizaron.');
    }

    // lo usa el botón "Aprobar proyecto" de la vista de detalle (fetch)
    public function cambiarEstado(Request $request, Proyecto $proyecto)
    {
        $this->autorizarProyecto($proyecto);

        $datos = $request->validate([
            'estado' => ['required', Rule::in(array_keys(Proyecto::ESTADOS))],
        ]);

        $proyecto->update($datos);
        session()->flash('mensaje', 'El estado del proyecto cambió a ' . Proyecto::ESTADOS[$proyecto->estado] . '.');

        return response()->json(['estado' => $proyecto->estado]);
    }

    public function destroy(Proyecto $proyecto)
    {
        $codigo = $proyecto->codigo_radicado;
        $proyecto->delete();

        return redirect()->route('dashboard')
            ->with('mensaje', "El proyecto {$codigo} fue eliminado.");
    }

    // admin: siempre. Estudiante: solo si es autor y el proyecto no está cerrado.
    private function autorizarEdicion(Proyecto $proyecto): void
    {
        $usuario = auth()->user();

        if ($usuario->esAdmin()) {
            return;
        }

        $puede = $usuario->esEstudiante()
            && $proyecto->esAutor($usuario)
            && ! in_array($proyecto->estado, ['APROBADO', 'RECHAZADO']);

        abort_unless($puede, 403, 'No puedes editar este proyecto.');
    }

    private function lineasExistentes()
    {
        return Proyecto::select('asunto_linea_investigacion')
            ->distinct()
            ->orderBy('asunto_linea_investigacion')
            ->pluck('asunto_linea_investigacion');
    }
}
