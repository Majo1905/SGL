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

        // el estudiante debe subir su proyecto en un documento al radicarlo (el admin puede omitirlo)
        $reglaDocumento = $this->isMethod('post')
            ? [Rule::requiredIf($this->user()?->esEstudiante()), 'nullable', 'file', 'mimes:pdf,doc,docx', 'max:10240']
            : ['prohibited'];

        return [
            'documento' => $reglaDocumento,
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
            'documento.required' => 'Debes subir tu proyecto en un documento (PDF, DOC o DOCX) para radicarlo.',
            'documento.mimes' => 'El documento debe estar en formato PDF, DOC o DOCX.',
            'documento.max' => 'El documento no puede superar los 10 MB.',
            'documento.uploaded' => 'No se pudo cargar el documento. Verifica que no supere los 10 MB.',
            'documento.prohibited' => 'Los documentos se cargan desde la ficha del proyecto.',
        ];
    }

    public function attributes()
    {
        return [
            'documento' => 'documento',
            'titulo' => 'título',
            'asunto_linea_investigacion' => 'línea de investigación',
        ];
    }
}
