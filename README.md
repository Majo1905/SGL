## Módulo de proyectos (CRUD)

Sistema de seguimiento de trabajos de grado. Después de iniciar sesión se puede:

- **Proyectos:** radicar, ver, editar y eliminar (el código `PRJ-AAAA-NNN` se genera solo).
- **Períodos académicos:** el administrador configura los períodos y define cuáles están activos; las radicaciones quedan vinculadas al período activo.
- **Observaciones:** registrar y editar por su autor, responder con evidencia como estudiante y aprobar o devolver como tutor/jurado.
- **Integrantes:** agregar y quitar autores, directores y jurados.

### Roles y requisitos funcionales (RF1 a RF10)

| Rol | Qué puede hacer |
|---|---|
| Administrador | Todo: usuarios, proyectos, integrantes, observaciones, informes y búsquedas |
| Tutor / Jurado | Ver sus proyectos, registrar y editar observaciones, aprobar, informe de pendientes, búsquedas |
| Estudiante | Radicar y editar su proyecto (mientras no esté cerrado), resolver observaciones, búsquedas |

- **RF1:** ingreso, registro público (siempre como estudiante), recuperación de contraseña, perfil, documento de identidad único y cuentas activables/desactivables. El administrador registra usuarios en `/usuarios`.
- **RF2–RF5:** ciclo de vida del proyecto, asignación de integrantes compatibles con su rol, visor central y trazabilidad de correcciones.
- **RF6:** `/informes/rango` (solo administrador). **RF7:** `/informes/pendientes`. **RF8:** `/informes/usuario` (solo administrador).
- **RF9 y RF10:** `/buscar` (código exacto y búsqueda parcial por título o línea).
- Las observaciones tienen tres estados: Pendiente, Resuelto y Aprobado. Los estudiantes deben registrar una respuesta para resolverlas; un evaluador asignado puede aprobarlas o devolverlas.

En una instalación existente ejecuta `php artisan migrate` para aplicar el esquema sin tocar los datos y luego crea el período en **Períodos** con una cuenta administradora. `php artisan migrate --seed` es solo para una base local vacía: carga los datos de demostración y un período inicial. Usuarios de demostración, todos con clave `12345678`: `admin@`, `tutor@`, `jurado@`, `estudiante1@` y `estudiante2@unicordoba.edu.co`.

### Funciones interactivas

- **Barra de avance con porcentaje**, en el detalle y en cada fila del dashboard. El cálculo está en `Proyecto::getPorcentajeAvanceAttribute()`: en revisión o rechazado = 30 %; con observaciones = de 30 % a 90 % según las resueltas; aprobado = 100 %.
- **Seguimiento de observaciones**: el estudiante registra evidencia de cumplimiento; tutor/jurado aprueba o devuelve el compromiso a pendiente. El filtro por estado no altera los indicadores del proyecto.
- **Estado automático:** al registrar una observación, un proyecto "Radicado" o "En revisión" pasa a "Con observaciones".
- **Semáforo de plazos** en cada observación (verde, amarillo si faltan 3 días o menos, rojo si venció) y panel de alertas en el dashboard.
- **Búsqueda y filtro por estado en vivo** en el listado de proyectos.

Puesta en marcha:

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
npm install && npm run build
php artisan serve
```

Usuario de prueba: `admin@unicordoba.edu.co` / `12345678`. Las pruebas se ejecutan con `php artisan test`.

<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
