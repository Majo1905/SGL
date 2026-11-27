<?php

namespace App\Http\Requests;

use App\Models\PeriodoAcademico;
use App\Models\Proyecto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProyectoRequest extends FormRequest
{
    public function rules(): array
    {
        $cantidadPeriodosActivos = PeriodoAcademico::where('estado', 'ACTIVO')->count();
        $reglaPeriodo = Rule::exists('periodos_academicos', 'id');
        if ($this->isMethod('post')) {
            $reglaPeriodo->where('estado', 'ACTIVO');
        }

        return [
            'titulo' => ['required', 'string', 'max:255'],
            'asunto_linea_investigacion' => ['required', 'string', 'max:255'],
            'resumen' => ['required', 'string', 'min:20', 'max:3000'],
            'estado' => ['nullable', Rule::in(array_keys(Proyecto::ESTADOS))],
            'periodo_academico_id' => [
                Rule::requiredIf($this->isMethod('post') && $cantidadPeriodosActivos !== 1),
                'nullable',
                $reglaPeriodo,
            ],
        ];
    }

    public function messages()
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'max' => 'El campo :attribute no puede superar los :max caracteres.',
            'resumen.min' => 'El resumen debe tener al menos :min caracteres.',
            'date' => 'El campo :attribute no es una fecha válida.',
            'estado.in' => 'El estado seleccionado no es válido.',
        ];
    }

    public function attributes()
    {
        return [
            'titulo' => 'título',
            'asunto_linea_investigacion' => 'línea de investigación',
        ];
    }
}
