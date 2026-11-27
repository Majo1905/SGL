<?php

namespace App\Http\Controllers;

use App\Models\PeriodoAcademico;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PeriodoAcademicoController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('rol:ADMIN')];
    }

    public function index()
    {
        return view('periodos.index', [
            'periodos' => PeriodoAcademico::withCount('proyectos')->orderByDesc('fecha_inicio')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:20', 'unique:periodos_academicos,nombre'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after_or_equal:fecha_inicio'],
        ]);
        DB::transaction(function () use ($datos): void {
            PeriodoAcademico::where('estado', 'ACTIVO')->update(['estado' => 'CERRADO']);
            $datos['estado'] = 'ACTIVO';
            PeriodoAcademico::create($datos);
        });

        return redirect()->route('periodos.index')->with('mensaje', 'Período académico creado.');
    }

    public function update(Request $request, PeriodoAcademico $periodo)
    {
        $datos = $request->validate([
            'estado' => ['required', Rule::in(array_keys(PeriodoAcademico::ESTADOS))],
        ]);

        DB::transaction(function () use ($periodo, $datos): void {
            if ($datos['estado'] === 'ACTIVO') {
                PeriodoAcademico::where('id', '!=', $periodo->id)
                    ->where('estado', 'ACTIVO')
                    ->update(['estado' => 'CERRADO']);
            }

            $periodo->update($datos);
        });

        return redirect()->route('periodos.index')->with('mensaje', 'Estado del período actualizado.');
    }
}
