<?php

namespace App\Http\Controllers;

use App\Models\Proyecto;

abstract class Controller
{
    // el administrador ve todo; los demás solo los proyectos donde participan
    protected function autorizarProyecto(Proyecto $proyecto): void
    {
        abort_unless($proyecto->esVisiblePara(auth()->user()), 403, 'No tienes acceso a este proyecto.');
    }
}
