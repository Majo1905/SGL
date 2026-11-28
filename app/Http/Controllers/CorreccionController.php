<?php

namespace App\Http\Controllers;

use App\Http\Requests\CorreccionRequest;
use App\Models\Correccion;
use App\Models\Proyecto;
use App\Models\User;
use App\Support\Avisos;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;

class CorreccionController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        // CU-09 y CU-10: las observaciones las formulan el tutor y el jurado evaluador
        return [
            new Middleware('rol:TUTOR,JURADO', only: ['store', 'edit', 'update', 'destroy']),
        ];
    }

    public function store(CorreccionRequest $request, Proyecto $proyecto)
    {
        $this->autorizarEvaluador($proyecto, $request->user());

        $correccion = $proyecto->correcciones()->create([
            ...$request->validated(),
            'usuario_autor_id' => $request->user()->id,
        ]);

        // si estaba en revisión, al tener una observación pasa a "con observaciones"
        $mensaje = 'Observación registrada.';
        if (in_array($proyecto->estado, ['RADICADO', 'EN_REVISION'], true)) {
            $proyecto->update(['estado' => 'CON_OBSERVACIONES']);
            $mensaje .= ' El proyecto pasó a estado "Con observaciones".';
        }

        Avisos::enviar(
            $proyecto->usuariosConRol('AUTOR_ESTUDIANTE'),
            'Nueva observación en tu proyecto',
            "{$request->user()->name} registró una observación en {$proyecto->codigo_radicado} con fecha límite {$correccion->fecha_limite->format('d/m/Y')}.",
            route('proyectos.show', $proyecto),
            $request->user(),
        );

        return redirect()->route('proyectos.show', $proyecto)->with('mensaje', $mensaje);
    }

    public function edit(Correccion $correccion)
    {
        $this->autorizarAutor($correccion);
        abort_unless($correccion->estado === 'PENDIENTE', 409, 'Solo puedes editar una observación pendiente.');

        return view('correcciones.edit', compact('correccion'));
    }

    public function update(CorreccionRequest $request, Correccion $correccion)
    {
        $this->autorizarAutor($correccion);
        abort_unless($correccion->estado === 'PENDIENTE', 409, 'Solo puedes editar una observación pendiente.');

        $correccion->update($request->validated());

        return redirect()->route('proyectos.show', $correccion->proyecto_id)
            ->with('mensaje', 'Observación actualizada.');
    }

    // CU-11: Pendiente -> Resuelto (estudiante) y Resuelto -> Aprobado / Pendiente (tutor o jurado)
    public function alternar(Request $request, Correccion $correccion)
    {
        $this->autorizarProyecto($correccion->proyecto);
        $usuario = $request->user();
        $datos = $request->validate([
            'estado' => ['required', Rule::in(['PENDIENTE', 'RESUELTO', 'APROBADO'])],
            'respuesta_estudiante' => ['nullable', 'string', 'max:5000'],
        ]);
        $this->validarTransicion($correccion, $usuario, $datos['estado'], $datos['respuesta_estudiante'] ?? null);

        $correccion->update([
            'estado' => $datos['estado'],
            'respuesta_estudiante' => $datos['estado'] === 'RESUELTO'
                ? trim((string) $datos['respuesta_estudiante'])
                : $correccion->respuesta_estudiante,
            'fecha_resolucion' => $datos['estado'] === 'PENDIENTE' ? null : now(),
        ]);

        $proyecto = $correccion->proyecto->load('correcciones');
        $this->notificarCambio($correccion, $proyecto, $usuario);

        return response()->json([
            'estado' => $correccion->estado,
            'porcentaje' => $proyecto->porcentaje_avance,
            'resueltas' => $proyecto->correcciones->whereIn('estado', ['RESUELTO', 'APROBADO'])->count(),
            'total' => $proyecto->correcciones->count(),
        ]);
    }

    public function destroy(Correccion $correccion)
    {
        $this->autorizarAutor($correccion);

        $proyectoId = $correccion->proyecto_id;
        $correccion->delete();

        return redirect()->route('proyectos.show', $proyectoId)
            ->with('mensaje', 'Observación eliminada.');
    }

    // tutor asignado, o jurado asignado cuando el tutor ya validó el proyecto
    private function esEvaluadorHabilitado(Proyecto $proyecto, User $usuario): bool
    {
        return $proyecto->esTutorAsignado($usuario)
            || ($proyecto->esJuradoAsignado($usuario) && $proyecto->estaValidado());
    }
    private function autorizarEvaluador(Proyecto $proyecto, User $usuario): void
    {
        $this->autorizarProyecto($proyecto);

        abort_if(
            $proyecto->esJuradoAsignado($usuario) && ! $proyecto->estaValidado(),
            409,
            'El tutor aún no ha validado el proyecto; todavía no puedes registrar observaciones.'
        );
        abort_unless(
            $this->esEvaluadorHabilitado($proyecto, $usuario),
            403,
            'Debes estar asignado como tutor o jurado para gestionar observaciones.'
        );
    }

    private function autorizarAutor(Correccion $correccion): void
    {
        $this->autorizarEvaluador($correccion->proyecto, auth()->user());
        abort_unless(
            $correccion->usuario_autor_id === auth()->id(),
            403,
            'Solo quien registró la observación puede modificarla.'
        );
    }

    private function validarTransicion(Correccion $correccion, User $usuario, string $estado, ?string $respuesta): void
    {
        $proyecto = $correccion->proyecto;
        $esAutorProyecto = $usuario->esEstudiante() && $proyecto->esAutor($usuario);

        $permitida = $estado === 'RESUELTO'
            && $correccion->estado === 'PENDIENTE'
            && $esAutorProyecto
            && filled($respuesta);

        $permitida = $permitida || (
            in_array($estado, ['APROBADO', 'PENDIENTE'], true)
            && $correccion->estado === 'RESUELTO'
            && $this->esEvaluadorHabilitado($proyecto, $usuario)
        );

        if ($estado === 'RESUELTO' && blank($respuesta)) {
            abort(422, 'Describe la respuesta o evidencia de cumplimiento antes de marcar la observación como resuelta.');
        }
        abort_unless($permitida, 422, 'La transición de estado no está permitida para tu rol o el estado actual.');
    }

    private function notificarCambio(Correccion $correccion, Proyecto $proyecto, User $usuario): void
    {
        $enlace = route('proyectos.show', $proyecto);

        if ($correccion->estado === 'RESUELTO') {
            // el estudiante respondió: se avisa a quien formuló la observación y al tutor
            $destinatarios = collect([$correccion->usuarioAutor])
                ->merge($proyecto->usuariosConRol('DIRECTOR_TUTOR'));
            Avisos::enviar($destinatarios, 'Observación respondida', "{$usuario->name} respondió una observación de {$proyecto->codigo_radicado}.", $enlace, $usuario);

            return;
        }

        $accion = $correccion->estado === 'APROBADO' ? 'aprobó tu respuesta a una observación' : 'devolvió una observación a pendiente';
        Avisos::enviar($proyecto->usuariosConRol('AUTOR_ESTUDIANTE'), 'Seguimiento de observaciones', "{$usuario->name} {$accion} en {$proyecto->codigo_radicado}.", $enlace, $usuario);
    }
}
