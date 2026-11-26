<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'rol'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    const ROLES = [
        'ADMIN' => 'Administrador',
        'TUTOR' => 'Tutor',
        'JURADO' => 'Jurado evaluador',
        'ESTUDIANTE' => 'Estudiante',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function esAdmin(): bool
    {
        return $this->rol === 'ADMIN';
    }

    public function esEstudiante(): bool
    {
        return $this->rol === 'ESTUDIANTE';
    }

    // tutores y jurados
    public function esDocente(): bool
    {
        return in_array($this->rol, ['TUTOR', 'JURADO'], true);
    }

    public function getNombreRolAttribute(): string
    {
        return self::ROLES[$this->rol] ?? 'Sin rol';
    }
}
