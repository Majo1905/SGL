<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;

class Proyecto extends Model
{
    use HasFactory;

    const ESTADOS = [
        'RADICADO' => 'Radicado',
        'EN_REVISION' => 'En revisión',
        'CON_OBSERVACIONES' => 'Con observaciones',
        'APROBADO' => 'Aprobado',
        'RECHAZADO' => 'Rechazado',
    ];

    protected $guarded = [];

    protected $casts = [
        'fecha_radicacion' => 'date',
        'validado_tutor_at' => 'datetime',
    ];

    public function usuarioCreador()
    {
        return $this->belongsTo(User::class, 'usuario_creador_id');
    }

    public function validadoPorTutor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validado_por_tutor_id');
    }

    public function periodoAcademico(): BelongsTo
    {
        return $this->belongsTo(PeriodoAcademico::class, 'periodo_academico_id');
    }

    public function correcciones()
    {
        return $this->hasMany(Correccion::class);
    }

    public function integrantes()
    {
        return $this->hasMany(IntegranteProyecto::class);
    }

    public function documentos()
    {
        return $this->hasMany(ProjectDocument::class)->latest();
    }

    /*
     * Porcentaje de avance del expediente:
     *  - radicado / en revisión / rechazado: 30%
     *  - con observaciones: de 30% a 90% según cuántas estén resueltas
     *  - aprobado: 100% (el último 10% es el aval del comité)
     */
    public function getPorcentajeAvanceAttribute()
    {
        if ($this->estado == 'APROBADO') {
            return 100;
        }

        if ($this->estado == 'CON_OBSERVACIONES') {
            $total = $this->correcciones->count();

            if ($total > 0) {
                $resueltas = $this->correcciones->whereIn('estado', ['RESUELTO', 'APROBADO'])->count();

                return (int) round(30 + 60 * $resueltas / $total);
            }
        }

        return 30;
    }

    // Admin ve todo; los demás solo los proyectos donde participan
    public function scopeVisiblesPara($consulta, User $usuario)
    {
        if ($usuario->esAdmin()) {
            return $consulta;
        }

        return $consulta->where(function ($q) use ($usuario) {
            $q->where('usuario_creador_id', $usuario->id)
                ->orWhereHas('integrantes', fn ($i) => $i->where('user_id', $usuario->id));
        });
    }

    public function esVisiblePara(User $usuario): bool
    {
        return $usuario->esAdmin()
            || $this->usuario_creador_id == $usuario->id
            || $this->integrantes()->where('user_id', $usuario->id)->exists();
    }

    public function esAutor(User $usuario): bool
    {
        return $this->usuario_creador_id == $usuario->id
            || $this->integrantes()->where('user_id', $usuario->id)->where('rol_en_proyecto', 'AUTOR_ESTUDIANTE')->exists();
    }

    // Genera el consecutivo del año, ej: PRJ-2026-004
    public static function siguienteCodigo()
    {
        $anio = now()->year;

        $ultimo = static::where('codigo_radicado', 'like', "PRJ-{$anio}-%")
            ->pluck('codigo_radicado')
            ->map(fn ($codigo) => (int) substr($codigo, strrpos($codigo, '-') + 1))
            ->max();

        return sprintf('PRJ-%d-%03d', $anio, ($ultimo ?? 0) + 1);
    }

    // ---------- roles dentro del proyecto ----------

    public function tienePersonaConRol(User $usuario, string $rolEnProyecto): bool
    {
        return $this->integrantes()
            ->where('user_id', $usuario->id)
            ->where('rol_en_proyecto', $rolEnProyecto)
            ->exists();
    }

    public function esTutorAsignado(User $usuario): bool
    {
        return $usuario->rol === 'TUTOR' && $this->tienePersonaConRol($usuario, 'DIRECTOR_TUTOR');
    }

    public function esJuradoAsignado(User $usuario): bool
    {
        return $usuario->rol === 'JURADO' && $this->tienePersonaConRol($usuario, 'JURADO_EVALUADOR');
    }

    // usuarios que participan con un rol (o varios) dentro del proyecto
    public function usuariosConRol(string ...$roles)
    {
        return User::whereIn('id', $this->integrantes()->whereIn('rol_en_proyecto', $roles)->pluck('user_id'))->get();
    }

    // el proyecto solo llega a los jurados cuando el tutor lo valida
    public function estaValidado(): bool
    {
        return $this->validado_tutor_at !== null;
    }

    public function estaCerrado(): bool
    {
        return in_array($this->estado, ['APROBADO', 'RECHAZADO'], true);
    }

    // el documento del proyecto lo consultan el admin, el autor, el tutor asignado y,
    // una vez validado por el tutor, los jurados asignados
    public function puedeConsultarDocumentos(User $usuario): bool
    {
        if ($usuario->esAdmin()) {
            return true;
        }
        if ($usuario->esEstudiante()) {
            return $this->esAutor($usuario);
        }
        if ($this->esTutorAsignado($usuario)) {
            return true;
        }

        return $this->esJuradoAsignado($usuario) && $this->estaValidado();
    }

    // guarda el archivo en el disco privado y lo registra como entrega del proyecto
    public function adjuntarDocumento(UploadedFile $archivo, User $usuario): ProjectDocument
    {
        $disco = config('filesystems.project_documents_disk');
        $ruta = $archivo->store("proyectos/{$this->id}/documentos", $disco);

        return $this->documentos()->create([
            'user_id' => $usuario->id,
            'original_name' => mb_substr($archivo->getClientOriginalName(), 0, 255),
            'path' => $ruta,
            'disk' => $disco,
            'mime_type' => $archivo->getMimeType() ?: 'application/octet-stream',
            'size' => $archivo->getSize(),
        ]);
    }
}
