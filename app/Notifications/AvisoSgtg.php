<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

// notificación interna: se guarda en la tabla "notifications" y se muestra en la campana del sistema
class AvisoSgtg extends Notification
{
    public function __construct(
        public string $titulo,
        public string $mensaje,
        public ?string $url = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'titulo' => $this->titulo,
            'mensaje' => $this->mensaje,
            'url' => $this->url,
        ];
    }
}
