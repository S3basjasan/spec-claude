<?php

use App\Modules\Estructura\Presentation\Controllers\CompetenciaController;
use App\Modules\Estructura\Presentation\Controllers\ComportamientoController;
use App\Modules\Estructura\Presentation\Controllers\PreguntaController;
use App\Modules\Estructura\Presentation\Controllers\PuestoController;
use Illuminate\Support\Facades\Route;

// Rutas del módulo Estructura.
// Los controladores viven en Presentation\Controllers y delegan a Application\UseCases.
Route::prefix('api/estructura')->middleware('api')->group(function () {
    // Route::get('/puestos', [\App\Modules\Estructura\Presentation\Controllers\ExampleController::class, 'index']);
});

// Diccionario de competencias (feature 001). Prefijo /api/competencias por contrato; ver specs/001-diccionario-competencias/plan.md.
// No existe DELETE: las competencias nunca se eliminan, se activan y desactivan.
Route::prefix('api/competencias')->middleware('api')->group(function () {
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/', [CompetenciaController::class, 'index'])->name('competencias.index');
        Route::get('/{id}', [CompetenciaController::class, 'show'])->whereNumber('id')->name('competencias.show');
    });

    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
        Route::post('/', [CompetenciaController::class, 'store'])->name('competencias.store');
        Route::put('/{id}', [CompetenciaController::class, 'update'])->whereNumber('id')->name('competencias.update');
        Route::patch('/{id}/estado', [CompetenciaController::class, 'cambiarEstado'])->whereNumber('id')->name('competencias.estado');
    });
});

// Diccionario de comportamientos (feature 002). Sin DELETE ni cambio de estado: el estado es el de la competencia.
Route::prefix('api/comportamientos')->middleware('api')->group(function () {
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/', [ComportamientoController::class, 'index'])->name('comportamientos.index');
    });

    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
        // Antes de `/{competencia_id}` para que el texto no se interprete como identificador.
        Route::get('/competencias-disponibles', [ComportamientoController::class, 'disponibles'])->name('comportamientos.disponibles');
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/{competenciaId}', [ComportamientoController::class, 'show'])->whereNumber('competenciaId')->name('comportamientos.show');
    });

    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
        Route::post('/', [ComportamientoController::class, 'store'])->name('comportamientos.store');
        Route::put('/{competenciaId}', [ComportamientoController::class, 'update'])->whereNumber('competenciaId')->name('comportamientos.update');
    });
});

// Diccionario de preguntas (feature 003). Sin DELETE ni cambio de estado: el estado es el de la competencia.
Route::prefix('api/preguntas')->middleware('api')->group(function () {
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/', [PreguntaController::class, 'index'])->name('preguntas.index');
    });

    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
        // Antes de `/{competenciaId}` para que el texto no se interprete como identificador.
        Route::get('/competencias-disponibles', [PreguntaController::class, 'disponibles'])->name('preguntas.disponibles');
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/{competenciaId}', [PreguntaController::class, 'show'])->whereNumber('competenciaId')->name('preguntas.show');
    });

    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
        Route::post('/', [PreguntaController::class, 'store'])->name('preguntas.store');
        Route::put('/{competenciaId}', [PreguntaController::class, 'update'])->whereNumber('competenciaId')->name('preguntas.update');
    });
});

// Catálogo de puestos (feature 004). Prefijo /api/puestos por contrato. No existe DELETE: los puestos nunca se
// eliminan, se activan y desactivan.
Route::prefix('api/puestos')->middleware('api')->group(function () {
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/', [PuestoController::class, 'index'])->name('puestos.index');
    });

    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
        // Antes de `/{puestoId}` para que el texto no se interprete como identificador.
        Route::get('/competencias-disponibles', [PuestoController::class, 'disponibles'])->name('puestos.disponibles');
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/{puestoId}', [PuestoController::class, 'show'])->whereNumber('puestoId')->name('puestos.show');
    });

    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
        Route::post('/', [PuestoController::class, 'store'])->name('puestos.store');
        Route::put('/{puestoId}', [PuestoController::class, 'update'])->whereNumber('puestoId')->name('puestos.update');
        Route::patch('/{puestoId}/estado', [PuestoController::class, 'estado'])->whereNumber('puestoId')->name('puestos.estado');
    });
});
