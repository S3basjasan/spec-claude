<?php

use App\Modules\Estructura\Presentation\Controllers\CompetenciaController;
use App\Modules\Estructura\Presentation\Controllers\EscalaController;
use App\Modules\Estructura\Presentation\Controllers\OrganigramaController;
use App\Modules\Estructura\Presentation\Controllers\PosicionController;
use App\Modules\Estructura\Presentation\Controllers\PuestoController;
use App\Modules\Estructura\Presentation\Controllers\TipoUnidadController;
use App\Modules\Estructura\Presentation\Controllers\UnidadController;
use Illuminate\Support\Facades\Route;

// Rutas del módulo Estructura.
// Los controladores viven en Presentation\Controllers y delegan a Application\UseCases.
Route::prefix('api/estructura')->middleware('api')->group(function () {
    // Route::get('/puestos', [\App\Modules\Estructura\Presentation\Controllers\ExampleController::class, 'index']);
});

// Diccionario de competencias unificado (feature 019: competencias, comportamientos y preguntas).
// Prefijo /api/competencias por contrato; ver specs/019-diccionario-competencias-unificado/plan.md.
// No existe DELETE: las competencias nunca se eliminan, se activan y desactivan.
Route::prefix('api/competencias')->middleware('api')->group(function () {
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/', [CompetenciaController::class, 'index'])->name('competencias.index');
        Route::get('/{id}', [CompetenciaController::class, 'show'])->whereNumber('id')->name('competencias.show');
    });

    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
        Route::post('/', [CompetenciaController::class, 'store'])->name('competencias.store');
        Route::put('/{id}', [CompetenciaController::class, 'update'])->whereNumber('id')->name('competencias.update');
        Route::put('/{id}/comportamientos', [CompetenciaController::class, 'actualizarComportamientos'])->whereNumber('id')->name('competencias.comportamientos');
        Route::put('/{id}/preguntas', [CompetenciaController::class, 'actualizarPreguntas'])->whereNumber('id')->name('competencias.preguntas');
        Route::patch('/{id}/escala', [CompetenciaController::class, 'aplicarEscala'])->whereNumber('id')->name('competencias.escala');
        Route::patch('/{id}/estado', [CompetenciaController::class, 'cambiarEstado'])->whereNumber('id')->name('competencias.estado');
    });
});

// Escalas con las que se nombran los grados (Letras, Números y Categorías).
Route::prefix('api/tipo-grados')->middleware(['api', 'auth:sanctum'])->group(function () {
    Route::get('/', [EscalaController::class, 'index'])->name('tipo-grados.index');
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

// Unidades organizacionales (feature 005). Prefijo /api/unidades por contrato. No existe DELETE: las unidades nunca se
// eliminan, se desactivan y se reactivan; el estado es propio de la unidad.
Route::prefix('api/unidades')->middleware('api')->group(function () {
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/', [UnidadController::class, 'index'])->name('unidades.index');
    });

    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
        // Antes de `/{unidadId}` para que el texto no se interprete como identificador.
        Route::get('/opciones', [UnidadController::class, 'opciones'])->name('unidades.opciones');
        Route::get('/{unidadId}/impacto-desactivacion', [UnidadController::class, 'impacto'])->whereNumber('unidadId')->name('unidades.impacto');
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/{unidadId}', [UnidadController::class, 'show'])->whereNumber('unidadId')->name('unidades.show');
    });

    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
        Route::post('/', [UnidadController::class, 'store'])->name('unidades.store');
        Route::put('/{unidadId}', [UnidadController::class, 'update'])->whereNumber('unidadId')->name('unidades.update');
        Route::patch('/{unidadId}/estado', [UnidadController::class, 'estado'])->whereNumber('unidadId')->name('unidades.estado');
    });
});

// Cadena de tipos de unidad (feature 005). Prefijo /api/tipos-unidad por contrato. No existe DELETE: los tipos nunca se
// eliminan. Ver la cadena es de cualquier autenticado; crear, renombrar y reordenar, del Administrador.
Route::prefix('api/tipos-unidad')->middleware('api')->group(function () {
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/', [TipoUnidadController::class, 'index'])->name('tipos-unidad.index');
    });

    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
        Route::post('/', [TipoUnidadController::class, 'store'])->name('tipos-unidad.store');
        Route::put('/', [TipoUnidadController::class, 'update'])->name('tipos-unidad.update');
    });
});

// Posiciones (feature 006). Prefijo /api/posiciones por contrato. No existe DELETE, ni reactivar, ni asignar ocupante:
// las posiciones nunca se eliminan, se suprimen; el ocupante lo gestiona el módulo de personal.
Route::prefix('api/posiciones')->middleware('api')->group(function () {
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/', [PosicionController::class, 'index'])->name('posiciones.index');
    });

    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
        // Antes de `/{posicionId}` para que el texto no se interprete como identificador.
        Route::get('/opciones', [PosicionController::class, 'opciones'])->name('posiciones.opciones');
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/{posicionId}', [PosicionController::class, 'show'])->whereNumber('posicionId')->name('posiciones.show');
    });

    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
        Route::post('/', [PosicionController::class, 'store'])->name('posiciones.store');
        Route::put('/{posicionId}', [PosicionController::class, 'update'])->whereNumber('posicionId')->name('posiciones.update');
        Route::post('/{posicionId}/suprimir', [PosicionController::class, 'suprimir'])->whereNumber('posicionId')->name('posiciones.suprimir');
    });
});

// Organigrama de posiciones (feature 007). Prefijo /api/organigrama por contrato. Solo lectura: no existen rutas de
// escritura y cualquier persona autenticada ve lo mismo.
Route::prefix('api/organigrama')->middleware(['api', 'auth:sanctum'])->group(function () {
    Route::get('/', [OrganigramaController::class, 'show'])->name('organigrama.show');
});
