<?php

use App\Modules\GestionPersonal\Presentation\Controllers\ColaboradorController;
use App\Modules\GestionPersonal\Presentation\Controllers\MiFichaController;
use Illuminate\Support\Facades\Route;

// Fichas de colaboradores (feature 005, GP-01). Prefijo /api/colaboradores por contrato.
// No existe DELETE: las fichas nunca se eliminan ni se desactivan.
Route::prefix('api/colaboradores')->middleware('api')->group(function () {
    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
        Route::get('/', [ColaboradorController::class, 'index'])->name('colaboradores.index');
        // Antes de `/{colaboradorId}` para que el texto no se interprete como identificador.
        Route::get('/filtros', [ColaboradorController::class, 'filtros'])->name('colaboradores.filtros');
        Route::post('/', [ColaboradorController::class, 'store'])->name('colaboradores.store');
        Route::get('/{colaboradorId}', [ColaboradorController::class, 'show'])->whereNumber('colaboradorId')->name('colaboradores.show');
        Route::patch('/{colaboradorId}', [ColaboradorController::class, 'update'])->whereNumber('colaboradorId')->name('colaboradores.update');
    });
});

// Consulta de la ficha propia: solo autenticación; el caso de uso exige asociación y permiso.
Route::prefix('api/mi-ficha')->middleware('api')->group(function () {
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/', [MiFichaController::class, 'show'])->name('mi-ficha.show');
    });
});
