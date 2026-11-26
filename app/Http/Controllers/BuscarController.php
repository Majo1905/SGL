<?php

namespace App\Http\Controllers;

use App\Models\Proyecto;
use Illuminate\Http\Request;

class BuscarController extends Controller
{
    // RF9 (?codigo=...) y RF10 (?q=...) comparten la misma pantalla
    public function index(Request $request)
    {
        $usuario = $request->user();
        $resultados = null;
        $aviso = null;

        // RF9: búsqueda exacta por código de radicado
        if ($request->has('codigo')) {
            $request->validateWithBag('codigo', [
                'codigo' => ['required', 'string', 'max:30'],
            ], [
                'codigo.required' => 'Ingresa un código de radicado.',
            ]);

            // se normaliza: mayúsculas y sin espacios
            $codigo = strtoupper(preg_replace('/\s+/', '', $request->input('codigo')));
            $proyecto = Proyecto::where('codigo_radicado', $codigo)->first();

            if (! $proyecto) {
                $aviso = 'No se encontró ningún proyecto con ese código.';
            } elseif (! $proyecto->esVisiblePara($usuario)) {
                $aviso = 'No tienes acceso a ese proyecto.';
            } else {
                return redirect()->route('proyectos.show', $proyecto);
            }
        }

        // RF10: búsqueda parcial (LIKE) por título o línea de investigación
        if ($request->has('q')) {
            $request->validateWithBag('titulo', [
                'q' => ['required', 'string', 'min:3', 'max:100'],
            ], [
                'q.required' => 'Escribe el término a buscar.',
                'q.min' => 'El término debe tener al menos :min caracteres.',
            ]);

            $termino = trim($request->input('q'));

            $resultados = Proyecto::visiblesPara($usuario)
                ->where(function ($q) use ($termino) {
                    $q->where('titulo', 'like', "%{$termino}%")
                        ->orWhere('asunto_linea_investigacion', 'like', "%{$termino}%");
                })
                ->orderBy('codigo_radicado')
                ->get();
        }

        return view('busqueda.index', compact('resultados', 'aviso'));
    }
}
