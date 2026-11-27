<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PeriodoAcademico extends Model
{
    protected $table = 'periodos_academicos';

    public const ESTADOS = [
        'ACTIVO' => 'Activo',
        'CERRADO' => 'Cerrado',
    ];

    protected $fillable = [
        'nombre',
        'fecha_inicio',
        'fecha_fin',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
        ];
    }

    public function proyectos(): HasMany
    {
        return $this->hasMany(Proyecto::class, 'periodo_academico_id');
    }
}
