<?php

use App\Modules\GestionPersonal\Presentation\Controllers\CategoriaDocumentalController;
use App\Modules\GestionPersonal\Presentation\Controllers\ColaboradorController;
use App\Modules\GestionPersonal\Presentation\Controllers\DesarrolloController;
use App\Modules\GestionPersonal\Presentation\Controllers\DocumentoColaboradorController;
use App\Modules\GestionPersonal\Presentation\Controllers\ExportacionFichaController;
use App\Modules\GestionPersonal\Presentation\Controllers\IngresoController;
use App\Modules\GestionPersonal\Presentation\Controllers\MiDesarrolloController;
use App\Modules\GestionPersonal\Presentation\Controllers\MiDocumentoController;
use App\Modules\GestionPersonal\Presentation\Controllers\MiExportacionController;
use App\Modules\GestionPersonal\Presentation\Controllers\MiFichaController;
use App\Modules\GestionPersonal\Presentation\Controllers\SituacionLaboralController;
use App\Modules\GestionPersonal\Presentation\Middleware\LimitarTamanoDeCarga;
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

        // Ingresos y reingresos (feature 006, GP-02). No existe PUT, PATCH ni DELETE: sin correcciones.
        Route::post('/{colaboradorId}/ingresos', [IngresoController::class, 'store'])->whereNumber('colaboradorId')->name('ingresos.store');
        Route::get('/{colaboradorId}/opciones-ingreso', [IngresoController::class, 'opciones'])->whereNumber('colaboradorId')->name('ingresos.opciones');
        Route::get('/{colaboradorId}/situacion-laboral', [SituacionLaboralController::class, 'situacion'])->whereNumber('colaboradorId')->name('situacion.show');
        Route::get('/{colaboradorId}/trayectoria', [SituacionLaboralController::class, 'trayectoria'])->whereNumber('colaboradorId')->name('trayectoria.show');
        // Desarrollo profesional (GP-03): formación, experiencia y movilidad. Sin DELETE.
        Route::get('/{colaboradorId}/desarrollo', [DesarrolloController::class, 'show'])->whereNumber('colaboradorId')->name('desarrollo.show');
        Route::post('/{colaboradorId}/formaciones', [DesarrolloController::class, 'storeFormacion'])->whereNumber('colaboradorId')->name('desarrollo.formaciones.store');
        Route::patch('/{colaboradorId}/formaciones/{formacionId}', [DesarrolloController::class, 'updateFormacion'])->whereNumber(['colaboradorId', 'formacionId'])->name('desarrollo.formaciones.update');
        Route::post('/{colaboradorId}/experiencias', [DesarrolloController::class, 'storeExperiencia'])->whereNumber('colaboradorId')->name('desarrollo.experiencias.store');
        Route::patch('/{colaboradorId}/experiencias/{experienciaId}', [DesarrolloController::class, 'updateExperiencia'])->whereNumber(['colaboradorId', 'experienciaId'])->name('desarrollo.experiencias.update');
        Route::patch('/{colaboradorId}/movilidad', [DesarrolloController::class, 'updateMovilidad'])->whereNumber('colaboradorId')->name('desarrollo.movilidad.update');

        // Documentos del colaborador (feature 014). Sin DELETE, restauración ni edición de contenido o metadatos.
        Route::get('/{colaborador}/documentos', [DocumentoColaboradorController::class, 'index'])->whereNumber('colaborador')->name('documentos.index');
        Route::post('/{colaborador}/documentos', [DocumentoColaboradorController::class, 'store'])->middleware(LimitarTamanoDeCarga::class)->whereNumber('colaborador')->name('documentos.store');
        Route::get('/{colaborador}/documentos/{documento}/contenido', [DocumentoColaboradorController::class, 'contenido'])->whereNumber(['colaborador', 'documento'])->name('documentos.contenido');
        Route::patch('/{colaborador}/documentos/{documento}/visibilidad', [DocumentoColaboradorController::class, 'updateVisibilidad'])->whereNumber(['colaborador', 'documento'])->name('documentos.visibilidad');
        Route::patch('/{colaborador}/documentos/{documento}/archivo', [DocumentoColaboradorController::class, 'archivar'])->whereNumber(['colaborador', 'documento'])->name('documentos.archivo');

        // Exportación individual de la ficha (feature 015): solo lectura, sin Idempotency-Key.
        Route::get('/{colaborador}/exportacion.pdf', [ExportacionFichaController::class, 'show'])->whereNumber('colaborador')->name('exportacion.show');
    });
});

// Consulta de la ficha propia: solo autenticación; el caso de uso exige asociación y permiso.
Route::prefix('api/mi-ficha')->middleware('api')->group(function () {
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/', [MiFichaController::class, 'show'])->name('mi-ficha.show');
        Route::get('/situacion-laboral', [SituacionLaboralController::class, 'miSituacion'])->name('mi-ficha.situacion');
        Route::get('/trayectoria', [SituacionLaboralController::class, 'miTrayectoria'])->name('mi-ficha.trayectoria');
        Route::get('/desarrollo', [MiDesarrolloController::class, 'show'])->name('mi-ficha.desarrollo');

        // Documentos propios: asociación válida y permiso documental, independiente del de ficha.
        Route::get('/documentos', [MiDocumentoController::class, 'index'])->name('mi-ficha.documentos');
        Route::get('/documentos/{documento}/contenido', [MiDocumentoController::class, 'contenido'])->whereNumber('documento')->name('mi-ficha.documentos.contenido');

        // Exportación de la ficha propia: asociación válida y permiso de exportación, independiente de los demás.
        Route::get('/exportacion.pdf', [MiExportacionController::class, 'show'])->name('mi-ficha.exportacion');
    });
});

// Catálogo de categorías documentales (feature 013). Prefijo /api/categorias-documentales por contrato.
// No existe DELETE: las categorías se desactivan, nunca se eliminan.
Route::prefix('api/categorias-documentales')->middleware('api')->group(function () {
    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
        Route::get('/', [CategoriaDocumentalController::class, 'index'])->name('categorias.index');
        Route::post('/', [CategoriaDocumentalController::class, 'store'])->name('categorias.store');
        Route::get('/{categoria}', [CategoriaDocumentalController::class, 'show'])->whereNumber('categoria')->name('categorias.show');
        Route::patch('/{categoria}', [CategoriaDocumentalController::class, 'update'])->whereNumber('categoria')->name('categorias.update');
        Route::patch('/{categoria}/estado', [CategoriaDocumentalController::class, 'updateEstado'])->whereNumber('categoria')->name('categorias.estado');
    });
});
