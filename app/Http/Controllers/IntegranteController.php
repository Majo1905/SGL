<?php

namespace App\Http\Controllers;

use App\Models\IntegranteProyecto;
use App\Models\Proyecto;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class IntegranteController extends Controller implements HasMiddleware
{
    // rol del usuario => único rol que puede tener dentro de un proyecto
    const ROL_COMPATIBLE = [
        'ESTUDIANTE' => 'AUTOR_ESTUDIANTE',
        'TUTOR' => 'DIRECTOR_TUTOR',
        'JURADO' => 'JURADO_EVALUADOR',
    ];

    public static function middleware(): array
    {
        return [new Middleware('rol:ADMIN')];
    }

    public function store(Request $request, Proyecto $proyecto)
    {
        $datos = $request->validateWithBag('integrante', [
            'user_id' => [
                'required',
                'exists:users,id',
                // un usuario no puede estar dos veces en el mismo proyecto
                Rule::unique('integrante_proyectos')->where('proyecto_id', $proyecto->id),
            ],
            'rol_en_proyecto' => ['required', Rule::in(array_keys(IntegranteProyecto::ROLES))],
        ], [
            'user_id.required' => 'Selecciona un usuario.',
            'user_id.exists' => 'El usuario seleccionado no existe.',
            'user_id.unique' => 'Ese usuario ya hace parte del proyecto.',
            'rol_en_proyecto.required' => 'Selecciona un rol.',
            'rol_en_proyecto.in' => 'El rol seleccionado no es válido.',
        ]);

        // el rol dentro del proyecto debe ser compatible con el rol del usuario
        $usuario = User::findOrFail($datos['user_id']);
        if ((self::ROL_COMPATIBLE[$usuario->rol] ?? null) !== $datos['rol_en_proyecto']) {
            throw ValidationException::withMessages([
                'rol_en_proyecto' => 'El rol del proyecto no es compatible con el rol del usuario (' . $usuario->nombre_rol . ').',
            ])->errorBag('integrante');
        }

        $proyecto->integrantes()->create($datos);

        return redirect()->route('proyectos.show', $proyecto)
            ->with('mensaje', 'Integrante agregado al proyecto.');
    }

    public function destroy(IntegranteProyecto $integrante)
    {
        $proyectoId = $integrante->proyecto_id;
        $integrante->delete();

        return redirect()->route('proyectos.show', $proyectoId)
            ->with('mensaje', 'Integrante retirado del proyecto.');
    }
}
