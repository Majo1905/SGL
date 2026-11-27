<?php

use App\Http\Controllers\BuscarController;
use App\Http\Controllers\CorreccionController;
use App\Http\Controllers\InformeController;
use App\Http\Controllers\IntegranteController;
use App\Http\Controllers\PeriodoAcademicoController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProyectoController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/login');
});

Route::middleware(['auth', 'verified', 'cuenta.activa'])->group(function () {
    // el dashboard es el listado de proyectos
    Route::get('/dashboard', [ProyectoController::class, 'index'])->name('dashboard');
    Route::resource('proyectos', ProyectoController::class)->except('index');

    // observaciones: crear va anidado al proyecto, el resto se hace por id
    Route::resource('proyectos.correcciones', CorreccionController::class)
        ->only(['store', 'edit', 'update', 'destroy'])
        ->parameters(['correcciones' => 'correccion'])
        ->shallow();

    Route::patch('/correcciones/{correccion}/alternar', [CorreccionController::class, 'alternar'])->name('correcciones.alternar');
    Route::patch('/proyectos/{proyecto}/estado', [ProyectoController::class, 'cambiarEstado'])->name('proyectos.estado');

    Route::post('/proyectos/{proyecto}/integrantes', [IntegranteController::class, 'store'])->name('integrantes.store');
    Route::delete('/integrantes/{integrante}', [IntegranteController::class, 'destroy'])->name('integrantes.destroy');

    // RF9 y RF10: búsquedas
    Route::get('/buscar', [BuscarController::class, 'index'])->name('buscar');

    // RF6, RF7 y RF8: informes
    Route::get('/informes/rango', [InformeController::class, 'rango'])->name('informes.rango');
    Route::get('/informes/pendientes', [InformeController::class, 'pendientes'])->name('informes.pendientes');
    Route::get('/informes/usuario', [InformeController::class, 'usuario'])->name('informes.usuario');

    // gestión de usuarios (solo administrador)
    Route::resource('usuarios', UsuarioController::class)->only(['index', 'create', 'store']);
    Route::patch('/usuarios/{usuario}/estado', [UsuarioController::class, 'cambiarEstado'])->name('usuarios.estado');
    Route::get('/periodos', [PeriodoAcademicoController::class, 'index'])->name('periodos.index');
    Route::post('/periodos', [PeriodoAcademicoController::class, 'store'])->name('periodos.store');
    Route::patch('/periodos/{periodo}', [PeriodoAcademicoController::class, 'update'])->name('periodos.update');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
