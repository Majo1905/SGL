# Deploy

## Opción A: Docker (Render, Railway, Fly.io, cualquier VPS con Docker)
1. Sube el proyecto a GitHub.
2. Crea un servicio web desde el Dockerfile.
3. Configura las variables de `.env.production.example` en el panel
   (APP_KEY: genérala con `php artisan key:generate --show`).
4. Conecta una base de datos MySQL/PostgreSQL (en esas plataformas SQLite se pierde al redeployar).
5. Al arrancar, el contenedor migra la base y crea el admin con ADMIN_EMAIL / ADMIN_PASSWORD.

Prueba local:
    docker build -t proyectos .
    docker run -p 8080:8080 --env-file .env proyectos

## Opción B: VPS o hosting con SSH
    cp .env.production.example .env   # edítalo
    ./deploy.sh
El document root debe apuntar a `public/`.

## Hosting compartido (cPanel)
Sube el proyecto fuera de `public_html`, copia el contenido de `public/` a `public_html`
y ajusta las rutas en `index.php`. Ejecuta `deploy.sh` por SSH o corre migrate/build en local.

## Notas
- NO uses `migrate:fresh --seed` en producción (borra todo y crea usuarios con clave 12345678).
- Cambia ADMIN_PASSWORD antes de publicar.
