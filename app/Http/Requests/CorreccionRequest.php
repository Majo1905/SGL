<?php

namespace App\Http\Requests;

use App\Models\Correccion;
use Illuminate\Foundation\Http\FormRequest;

class CorreccionRequest extends FormRequest
{
    // los errores se leen con $errors->correccion porque la vista del proyecto tiene varios formularios
    protected $errorBag = 'correccion';

    public function rules()
    {
        $reglaFecha = ['required', 'date'];
        if ($this->isMethod('post')) {
            $reglaFecha[] = 'after:today';
        }

        return [
            'descripcion_observacion' => ['required', 'string', 'min:10', 'max:1000'],
            'fecha_limite' => $reglaFecha,
        ];
    }

    public function messages()
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'descripcion_observacion.min' => 'La observación debe tener al menos :min caracteres.',
            'descripcion_observacion.max' => 'La observación no puede superar los :max caracteres.',
            'fecha_limite.date' => 'La fecha límite no es válida.',
            'fecha_limite.after' => 'La fecha límite debe ser posterior a la fecha actual.',
            'estado.in' => 'El estado seleccionado no es válido.',
        ];
    }

    public function attributes()
    {
        return [
            'descripcion_observacion' => 'observación',
            'fecha_limite' => 'fecha límite',
        ];
    }
}
