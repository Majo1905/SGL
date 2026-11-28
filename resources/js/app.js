

import Alpine from 'alpinejs';

window.Alpine = Alpine;

// campana de notificaciones: consulta periódicamente cuántas hay sin leer
Alpine.data('campana', (inicial, url) => ({
    noLeidas: inicial,
    iniciar() {
        setInterval(async () => {
            try {
                const r = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                if (r.ok) this.noLeidas = (await r.json()).no_leidas;
            } catch (e) { /* sin conexión: se reintenta en el siguiente ciclo */ }
        }, 30000);
    },
}));

Alpine.start();
