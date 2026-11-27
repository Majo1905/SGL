<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Correccion extends Model
{
    const ESTADOS = [
        'PENDIENTE' => 'Pendiente',
        'RESUELTO' => 'Resuelto',
        'APROBADO' => 'Aprobado',
    ];

    protected $fillable = [
        'proyecto_id',
        'usuario_autor_id',
        'descripcion_observacion',
        'fecha_limite',
        'estado',
        'respuesta_estudiante',
        'fecha_resolucion',
    ];

    protected function casts(): array
    {
        return [
            'fecha_limite' => 'date',
            'fecha_resolucion' => 'datetime',
        ];
    }

    // positivo = faltan días, 0 = vence hoy, negativo = ya venció
    public function getDiasRestantesAttribute()
    {
        return (int) today()->diffInDays($this->fecha_limite, false);
    }

    // solo las pendientes tienen semáforo: rojo (vencida), amarillo (3 días o menos), verde
    public function getSemaforoAttribute()
    {
        if (in_array($this->estado, ['RESUELTO', 'APROBADO'])) {
            return 'verde';
        }

        if ($this->dias_restantes < 0) {
            return 'rojo';
        }

        return $this->dias_restantes <= 3 ? 'amarillo' : 'verde';
    }

    public function getTextoPlazoAttribute()
    {
        $dias = $this->dias_restantes;

        if ($dias < 0) {
            return 'Vencida hace '.abs($dias).($dias == -1 ? ' día' : ' días');
        }
        if ($dias == 0) {
            return 'Vence hoy';
        }

        return 'Faltan '.$dias.($dias == 1 ? ' día' : ' días');
    }

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class);
    }

    public function usuarioAutor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_autor_id');
    }
}
