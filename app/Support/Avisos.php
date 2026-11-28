<?php

namespace App\Support;

use App\Models\User;
use App\Notifications\AvisoSgtg;
use Illuminate\Support\Collection;

class Avisos
{
    /**
     * Envía una notificación a los usuarios indicados (sin repetidos, solo cuentas activas
     * y sin avisar a quien ejecutó la acción).
     *
     * @param  iterable<User>  $usuarios
     */
    public static function enviar(iterable $usuarios, string $titulo, string $mensaje, ?string $url = null, ?User $excepto = null): void
    {
        Collection::make($usuarios)
            ->filter(fn (?User $u) => $u && $u->activo && (! $excepto || $u->id !== $excepto->id))
            ->unique('id')
            ->each(fn (User $u) => $u->notify(new AvisoSgtg($titulo, $mensaje, $url)));
    }
}
