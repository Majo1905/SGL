<?php

namespace App\Http\Controllers;

use App\Http\Requests\CorreccionRequest;
use App\Models\Correccion;
use App\Models\Proyecto;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class CorreccionController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        // registrar, editar y eliminar observaciones: admin, tutores y jurados.
        // marcar como resuelta (alternar) lo puede hacer cualquier rol con acceso al proyecto.
        return [
            new Middleware('rol:ADMIN,TUTOR,JURADO', only: ['store', 'edit', 'update', 'destroy']),
        ];
    }

    public function store(CorreccionRequest $request, Proyecto $proyecto)
    {
        $this->autorizarProyecto($proyecto);

        $proyecto->correcciones()->create($request->validated());

        // si estaba en revisión, al tener una observación pasa a "con observaciones"
        $mensaje = 'Observación registrada.';
        if ($proyecto->estado == 'EN_REVISION') {
            $proyecto->update(['estado' => 'CON_OBSERVACIONES']);
            $mensaje .= ' El proyecto pasó a estado "Con observaciones".';
        }

        return redirect()->route('proyectos.show', $proyecto)->with('mensaje', $mensaje);
    }

    public function edit(Correccion $correccion)
    {
        $this->autorizarProyecto($correccion->proyecto);

        return view('correcciones.edit', compact('correccion'));
    }

    public function update(CorreccionRequest $request, Correccion $correccion)
    {
        $this->autorizarProyecto($correccion->proyecto);

        $correccion->update($request->validated());

        return redirect()->route('proyectos.show', $correccion->proyecto_id)
            ->with('mensaje', 'Observación actualizada.');
    }

    // marca como resuelta (o vuelve a pendiente) y devuelve el avance actualizado
    public function alternar(Correccion $correccion)
    {
        $this->autorizarProyecto($correccion->proyecto);

        // una observación aprobada por el jurado/tutor ya no se reabre desde el botón
        abort_if($correccion->estado == 'APROBADO', 409, 'La observación ya fue aprobada.');

        $correccion->update([
            'estado' => $correccion->estado == 'RESUELTO' ? 'PENDIENTE' : 'RESUELTO',
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
        $this->autorizarProyecto($correccion->proyecto);

        $proyectoId = $correccion->proyecto_id;
        $correccion->delete();

        return redirect()->route('proyectos.show', $proyectoId)
            ->with('mensaje', 'Observación eliminada.');
    }
}
