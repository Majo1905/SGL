<?php

namespace App\Http\Controllers;

use App\Http\Requests\CorreccionRequest;
use App\Models\Correccion;
use App\Models\Proyecto;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;

class CorreccionController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('rol:ADMIN,TUTOR,JURADO', only: ['store', 'edit', 'update', 'destroy']),
        ];
    }

    public function store(CorreccionRequest $request, Proyecto $proyecto)
    {
        $this->autorizarEvaluador($proyecto, $request->user());

        $proyecto->correcciones()->create([
            ...$request->validated(),
            'usuario_autor_id' => $request->user()->id,
        ]);

        // si estaba en revisión, al tener una observación pasa a "con observaciones"
        $mensaje = 'Observación registrada.';
        if (in_array($proyecto->estado, ['RADICADO', 'EN_REVISION'], true)) {
            $proyecto->update(['estado' => 'CON_OBSERVACIONES']);
            $mensaje .= ' El proyecto pasó a estado "Con observaciones".';
        }

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

    private function autorizarEvaluador(Proyecto $proyecto, User $usuario): void
    {
        $this->autorizarProyecto($proyecto);

        $esEvaluadorAsignado = $proyecto->integrantes()
            ->where('user_id', $usuario->id)
            ->whereIn('rol_en_proyecto', ['DIRECTOR_TUTOR', 'JURADO_EVALUADOR'])
            ->exists();

        abort_unless($usuario->esAdmin() || $esEvaluadorAsignado, 403, 'Debes estar asignado como tutor o jurado para gestionar observaciones.');
    }

    private function autorizarAutor(Correccion $correccion): void
    {
        $this->autorizarEvaluador($correccion->proyecto, auth()->user());
        abort_unless(
            auth()->user()->esAdmin() || $correccion->usuario_autor_id === auth()->id(),
            403,
            'Solo quien registró la observación puede modificarla.'
        );
    }

    private function validarTransicion(Correccion $correccion, User $usuario, string $estado, ?string $respuesta): void
    {
        $esAutorProyecto = $correccion->proyecto->esAutor($usuario);
        $esEvaluadorAsignado = $correccion->proyecto->integrantes()
            ->where('user_id', $usuario->id)
            ->whereIn('rol_en_proyecto', ['DIRECTOR_TUTOR', 'JURADO_EVALUADOR'])
            ->exists();

        $permitida = $estado === 'RESUELTO'
            && $correccion->estado === 'PENDIENTE'
            && ($usuario->esAdmin() || ($usuario->esEstudiante() && $esAutorProyecto))
            && filled($respuesta);

        $permitida = $permitida || (
            in_array($estado, ['APROBADO', 'PENDIENTE'], true)
            && $correccion->estado === 'RESUELTO'
            && ($usuario->esAdmin() || $esEvaluadorAsignado)
        );

        if ($estado === 'RESUELTO' && blank($respuesta)) {
            abort(422, 'Describe la respuesta o evidencia de cumplimiento antes de marcar la observación como resuelta.');
        }
        abort_unless($permitida, 422, 'La transición de estado no está permitida para tu rol o el estado actual.');
    }
}
