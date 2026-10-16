<?php

use App\Modules\Desarrollo\Presentation\Controllers\AreaFormativaController;
use App\Modules\Desarrollo\Presentation\Controllers\CatalogoFormativoController;
use App\Modules\Desarrollo\Presentation\Controllers\DnfController;
use Illuminate\Support\Facades\Route;

// Catálogo formativo de competencias (feature 005). Prefijo /api/catalogo-formativo por contrato.
// No existe DELETE: los vínculos con cursos y micro-contenidos se quitan con PUT y las áreas formativas no se eliminan.
Route::prefix('api/catalogo-formativo')->middleware('api')->group(function () {
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/', [CatalogoFormativoController::class, 'index'])->name('catalogo-formativo.index');
        Route::get('/recomendados', [CatalogoFormativoController::class, 'recomendados'])->name('catalogo-formativo.recomendados');
        Route::get('/areas', [AreaFormativaController::class, 'index'])->name('catalogo-formativo.areas.index');
    });

    // Antes de `/{competenciaId}` para que estas palabras nunca se interpreten como identificador.
    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
        Route::get('/cobertura', [CatalogoFormativoController::class, 'cobertura'])->name('catalogo-formativo.cobertura');
        Route::get('/recursos-disponibles', [CatalogoFormativoController::class, 'recursosDisponibles'])->name('catalogo-formativo.recursos');
        Route::post('/areas', [AreaFormativaController::class, 'store'])->name('catalogo-formativo.areas.store');
        Route::patch('/areas/{areaId}', [AreaFormativaController::class, 'update'])->whereNumber('areaId')->name('catalogo-formativo.areas.update');
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/{competenciaId}', [CatalogoFormativoController::class, 'show'])->whereNumber('competenciaId')->name('catalogo-formativo.show');
    });

    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
        Route::put('/{competenciaId}', [CatalogoFormativoController::class, 'update'])->whereNumber('competenciaId')->name('catalogo-formativo.update');
        Route::get('/{competenciaId}/historial', [CatalogoFormativoController::class, 'historial'])->whereNumber('competenciaId')->name('catalogo-formativo.historial');
    });
});

// Detección de necesidades formativas (feature 006). Prefijo /api/dnf por contrato.
// No existen PUT, PATCH ni DELETE: una DNF es un diagnóstico puntual e inmutable.
// Las rutas fijas (`opciones`) se declaran antes de cualquier `/{id}` para que nunca se interpreten como identificador.
Route::prefix('api/dnf')->middleware('api')->group(function () {
    // Diagnóstico propio del colaborador: solo autenticación; los datos son siempre los del usuario del token.
    Route::get('/mi-diagnostico', [DnfController::class, 'miDiagnostico'])->middleware('auth:sanctum')->name('dnf.mi-diagnostico');

    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
        Route::get('/opciones', [DnfController::class, 'opciones'])->name('dnf.opciones');
        Route::get('/', [DnfController::class, 'index'])->name('dnf.index');
        Route::post('/', [DnfController::class, 'store'])->name('dnf.store');
        Route::get('/{id}', [DnfController::class, 'show'])->whereNumber('id')->name('dnf.show');
        Route::post('/{id}/plan', [DnfController::class, 'plan'])->whereNumber('id')->name('dnf.plan');
    });
});
