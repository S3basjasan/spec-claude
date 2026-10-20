<?php

use App\Modules\Compensaciones\Presentation\Controllers\CategoriaController;
use App\Modules\Compensaciones\Presentation\Controllers\CompetenciasController;
use Illuminate\Support\Facades\Route;

// Rutas del módulo Compensaciones: categorías de reconocimiento (feature 016).
// No existe DELETE: las categorías nunca se eliminan, se activan y desactivan.
Route::prefix('api/compensaciones')->middleware('api')->group(function () {
    // Lectura: cualquier usuario autenticado.
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/categorias', [CategoriaController::class, 'index'])->name('compensaciones.categorias.index');
        Route::get('/categorias/{categoria}', [CategoriaController::class, 'show'])->whereNumber('categoria')->name('compensaciones.categorias.show');
        Route::get('/competencias-filtro', [CompetenciasController::class, 'filtro'])->name('compensaciones.competencias.filtro');
    });

    // Gestión: solo el Administrador.
    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
        Route::post('/categorias', [CategoriaController::class, 'store'])->name('compensaciones.categorias.store');
        Route::put('/categorias/{categoria}', [CategoriaController::class, 'update'])->whereNumber('categoria')->name('compensaciones.categorias.update');
        Route::patch('/categorias/{categoria}/estado', [CategoriaController::class, 'cambiarEstado'])->whereNumber('categoria')->name('compensaciones.categorias.estado');
        Route::get('/competencias-disponibles', [CompetenciasController::class, 'disponibles'])->name('compensaciones.competencias.disponibles');
    });
});
