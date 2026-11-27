# Despliegue en Render

## Publicar con Blueprint

1. Sube este proyecto a un repositorio GitHub y conéctalo a Render.
2. En Render, crea un **Blueprint** desde ese repositorio. Render leerá `render.yaml` y propondrá un servicio web Docker y una base PostgreSQL.
3. Antes de crear los servicios, configura los valores secretos solicitados: `APP_KEY`, `ADMIN_EMAIL` y `ADMIN_PASSWORD`. Genera `APP_KEY` localmente con `php artisan key:generate --show`; usa una contraseña robusta y distinta de las cuentas de demostración.
4. Acepta la creación de los servicios. El contenedor migra la base de datos y crea/actualiza el administrador inicial al arrancar. Revisa los logs; el despliegue no se considera listo si la migración o la creación del administrador falla.
5. Abre la URL asignada por Render y prueba el inicio de sesión con el correo y la contraseña de administrador configurados.

No uses las credenciales de demostración en producción. Nunca subas `.env`, claves ni contraseñas a Git.

## Persistencia y costos

El Blueprint usa los planes gratuitos para facilitar una demostración, pero tienen límites importantes: el servicio puede suspenderse por inactividad y la base de datos gratuita de Render vence a los 30 días. Además, el sistema de archivos local del servicio web es efímero; los documentos cargados no se conservan de forma garantizada después de un reinicio o despliegue. Esta configuración no es adecuada para datos reales.

Para conservar datos, cambia a una base de datos con retención adecuada y configura almacenamiento persistente para documentos antes de usarlo con información real. Un disco persistente de Render requiere un plan de servicio compatible y debe montarse en `/var/www/html/storage/app/private`. La integración S3 no está incluida en esta entrega; no configures `PROJECT_DOCUMENTS_DISK=s3` sin agregar primero el adaptador S3 de Flysystem.

## Comprobación local con Docker

```bash
docker build -t sgtg .
docker run --rm -p 8080:8080 --env-file .env sgtg
```

`.env` debe contener como mínimo `APP_KEY`, `APP_ENV=production`, `APP_DEBUG=false`, `DB_CONNECTION`/la configuración de base de datos, `ADMIN_EMAIL` y `ADMIN_PASSWORD`. El contenedor ejecuta `php artisan migrate --force` en cada inicio, nunca `migrate:fresh`.
