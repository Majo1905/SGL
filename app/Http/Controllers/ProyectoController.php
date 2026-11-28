<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProyectoRequest;
use App\Models\Correccion;
use App\Models\PeriodoAcademico;
use App\Models\Proyecto;
use App\Models\User;
use App\Support\Avisos;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProyectoController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('rol:ADMIN,ESTUDIANTE', only: ['create', 'store']),
            new Middleware('rol:ADMIN', only: ['destroy']),
            // concepto final: lo emite el jurado evaluador (o el administrador)
            new Middleware('rol:ADMIN,JURADO', only: ['cambiarEstado']),
            // aprobación previa antes del envío a jurados: solo el tutor
            new Middleware('rol:TUTOR', only: ['validarTutor']),
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

        $periodoActivo = PeriodoAcademico::where('estado', 'ACTIVO')->orderByDesc('fecha_inicio')->first();

        return view('dashboard', compact('proyectos', 'alertas', 'periodoActivo'));
    }

    public function create()
    {
        return view('proyectos.create', [
            'proyecto' => new Proyecto,
            'lineas' => $this->lineasExistentes(),
            'periodos' => PeriodoAcademico::where('estado', 'ACTIVO')->orderByDesc('fecha_inicio')->get(),
        ]);
    }

    public function store(ProyectoRequest $request)
    {
        $usuario = $request->user();

        $datos = $request->validated();
        $archivo = $request->file('documento');
        unset($datos['estado'], $datos['documento']);
        $datos['estado'] = 'RADICADO';
        $datos['fecha_radicacion'] = today()->toDateString();
        $datos['periodo_academico_id'] ??= PeriodoAcademico::where('estado', 'ACTIVO')->value('id');
        $datos['codigo_radicado'] = Proyecto::siguienteCodigo();
        $datos['usuario_creador_id'] = $usuario->id;

        $proyecto = DB::transaction(function () use ($datos, $usuario, $archivo) {
            $proyecto = Proyecto::create($datos);

            // si radica un estudiante, queda vinculado automáticamente como autor
            if ($usuario->esEstudiante()) {
                $proyecto->integrantes()->create([
                    'user_id' => $usuario->id,
                    'rol_en_proyecto' => 'AUTOR_ESTUDIANTE',
                ]);
            }

            // el proyecto del estudiante queda en un documento que luego revisa el tutor
            if ($archivo) {
                $proyecto->adjuntarDocumento($archivo, $usuario);
            }

            return $proyecto;
        });

        Avisos::enviar(
            User::where('rol', 'ADMIN')->get(),
            'Nuevo proyecto radicado',
            "{$usuario->name} radicó el proyecto {$proyecto->codigo_radicado}. Asigna su tutor y jurados.",
            route('proyectos.show', $proyecto),
            $usuario,
        );

        return redirect()->route('proyectos.show', $proyecto)
            ->with('mensaje', "Proyecto {$proyecto->codigo_radicado} radicado correctamente.");
    }

    public function show(Proyecto $proyecto)
    {
        $this->autorizarProyecto($proyecto);

        $proyecto->load([
            'correcciones' => fn ($query) => $query->with('usuarioAutor')->orderBy('fecha_limite'),
            'integrantes.user',
            'periodoAcademico',
            'documentos.usuario',
            'validadoPorTutor',
        ]);

        // solo se ofrecen los usuarios que todavía no hacen parte del proyecto
        $usuarios = User::whereNotIn('id', $proyecto->integrantes->pluck('user_id'))
            ->where('rol', '!=', 'ADMIN')
            ->where('activo', true)
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
            'periodos' => collect(),
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

    // El tutor valida el avance del estudiante y lo envía a los jurados evaluadores.
    public function validarTutor(Request $request, Proyecto $proyecto)
    {
        $usuario = $request->user();

        abort_unless($proyecto->esTutorAsignado($usuario), 403, 'Solo el tutor asignado puede validar el proyecto.');

        if ($proyecto->estaCerrado()) {
            return back()->with('error', 'El proyecto ya está cerrado.');
        }
        if ($proyecto->estaValidado()) {
            return back()->with('error', 'El proyecto ya fue validado y enviado a los jurados.');
        }
        if (! $proyecto->documentos()->exists()) {
            return back()->with('error', 'El estudiante todavía no ha cargado el documento del proyecto.');
        }
        if ($proyecto->correcciones()->where('estado', 'PENDIENTE')->exists()) {
            return back()->with('error', 'Aún hay observaciones pendientes. Apruébalas antes de enviar el proyecto a los jurados.');
        }

        $proyecto->update([
            'estado' => 'EN_REVISION',
            'validado_por_tutor_id' => $usuario->id,
            'validado_tutor_at' => now(),
        ]);

        $enlace = route('proyectos.show', $proyecto);
        Avisos::enviar($proyecto->usuariosConRol('JURADO_EVALUADOR'), 'Proyecto listo para evaluación', "El tutor validó el proyecto {$proyecto->codigo_radicado}. Ya puedes revisar el documento y registrar observaciones.", $enlace, $usuario);
        Avisos::enviar($proyecto->usuariosConRol('AUTOR_ESTUDIANTE'), 'Tu proyecto fue validado por el tutor', "{$proyecto->codigo_radicado} fue enviado a los jurados evaluadores.", $enlace, $usuario);

        return redirect()->route('proyectos.show', $proyecto)
            ->with('mensaje', 'Proyecto validado y enviado a los jurados evaluadores.');
    }

    // Concepto del jurado evaluador: Aprobado, Con observaciones o Rechazado.
    public function cambiarEstado(Request $request, Proyecto $proyecto)
    {
        $this->autorizarProyecto($proyecto);
        $usuario = $request->user();

        $datos = $request->validate([
            'estado' => ['required', Rule::in(array_keys(Proyecto::ESTADOS))],
        ]);

        if (! $usuario->esAdmin()) {
            abort_unless($proyecto->esJuradoAsignado($usuario), 403, 'Solo un jurado asignado puede emitir el concepto.');
            abort_unless($proyecto->estaValidado(), 409, 'El tutor aún no ha validado el proyecto.');
            abort_unless(
                in_array($datos['estado'], ['APROBADO', 'CON_OBSERVACIONES', 'RECHAZADO'], true),
                422,
                'El concepto debe ser Aprobado, Con observaciones o Rechazado.'
            );
        }

        if ($datos['estado'] === 'APROBADO' && $proyecto->correcciones()->where('estado', 'PENDIENTE')->exists()) {
            abort(422, 'No se puede aprobar un proyecto con observaciones pendientes.');
        }

        $proyecto->update($datos);

        Avisos::enviar(
            $proyecto->usuariosConRol('AUTOR_ESTUDIANTE', 'DIRECTOR_TUTOR'),
            'Concepto sobre el proyecto',
            "El proyecto {$proyecto->codigo_radicado} quedó en estado: ".Proyecto::ESTADOS[$proyecto->estado].'.',
            route('proyectos.show', $proyecto),
            $usuario,
        );

        session()->flash('mensaje', 'El estado del proyecto cambió a '.Proyecto::ESTADOS[$proyecto->estado].'.');

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
