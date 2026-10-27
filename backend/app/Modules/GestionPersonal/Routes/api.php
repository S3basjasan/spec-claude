<?php

use App\Modules\GestionPersonal\Domain\ValueObjects\TipoDePolitica;
use App\Modules\GestionPersonal\Presentation\Controllers\ActuacionController;
use App\Modules\GestionPersonal\Presentation\Controllers\CategoriaDocumentalController;
use App\Modules\GestionPersonal\Presentation\Controllers\ColaboradorController;
use App\Modules\GestionPersonal\Presentation\Controllers\DesarrolloController;
use App\Modules\GestionPersonal\Presentation\Controllers\DocumentoColaboradorController;
use App\Modules\GestionPersonal\Presentation\Controllers\ExportacionFichaController;
use App\Modules\GestionPersonal\Presentation\Controllers\IngresoController;
use App\Modules\GestionPersonal\Presentation\Controllers\MiDesarrolloController;
use App\Modules\GestionPersonal\Presentation\Controllers\MiDesvinculacionController;
use App\Modules\GestionPersonal\Presentation\Controllers\MiDocumentoController;
use App\Modules\GestionPersonal\Presentation\Controllers\MiExportacionController;
use App\Modules\GestionPersonal\Presentation\Controllers\MiFichaController;
use App\Modules\GestionPersonal\Presentation\Controllers\MiSolicitudController;
use App\Modules\GestionPersonal\Presentation\Controllers\OffboardingController;
use App\Modules\GestionPersonal\Presentation\Controllers\PoliticaAplicableController;
use App\Modules\GestionPersonal\Presentation\Controllers\PoliticaBorradorController;
use App\Modules\GestionPersonal\Presentation\Controllers\PoliticaPersonalController;
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

// Políticas operativas de personal (feature 016). Sin DELETE ni PUT: una versión publicada no se edita ni se elimina.
Route::prefix('api/politicas-personal')->middleware('api')->group(function () {
    $tipo = implode('|', TipoDePolitica::identificadores());

    // Condiciones aplicables: lectura informativa para cualquier usuario autenticado del tenant (no expone borradores ni auditoría).
    Route::middleware('auth:sanctum')->get('/{tipo}/aplicable', [PoliticaAplicableController::class, 'show'])->where('tipo', $tipo)->name('politicas.aplicable');

    Route::middleware(['auth:sanctum', 'admin'])->group(function () use ($tipo) {
        Route::get('/', [PoliticaPersonalController::class, 'index'])->name('politicas.index');
        Route::get('/{tipo}', [PoliticaPersonalController::class, 'show'])->where('tipo', $tipo)->name('politicas.show');

        Route::get('/{tipo}/versiones/{version}', [PoliticaPersonalController::class, 'version'])->where('tipo', $tipo)->whereNumber('version')->name('politicas.version');
        Route::get('/{tipo}/versiones/{version}/comparacion', [PoliticaPersonalController::class, 'comparacion'])->where('tipo', $tipo)->whereNumber('version')->name('politicas.comparacion');
        Route::post('/{tipo}/versiones/{version}/retiro', [PoliticaPersonalController::class, 'retirar'])->where('tipo', $tipo)->whereNumber('version')->name('politicas.retiro');

        Route::post('/{tipo}/borrador', [PoliticaBorradorController::class, 'store'])->where('tipo', $tipo)->name('politicas.borrador.store');
        Route::patch('/{tipo}/borrador/{version}', [PoliticaBorradorController::class, 'update'])->where('tipo', $tipo)->whereNumber('version')->name('politicas.borrador.update');
        Route::post('/{tipo}/borrador/{version}/descarte', [PoliticaBorradorController::class, 'descartar'])->where('tipo', $tipo)->whereNumber('version')->name('politicas.borrador.descarte');
        Route::post('/{tipo}/borrador/{version}/previsualizacion', [PoliticaBorradorController::class, 'previsualizar'])->where('tipo', $tipo)->whereNumber('version')->name('politicas.borrador.previsualizacion');
        Route::post('/{tipo}/borrador/{version}/publicacion', [PoliticaBorradorController::class, 'publicar'])->where('tipo', $tipo)->whereNumber('version')->name('politicas.borrador.publicacion');
    });
});

// Movimientos y novedades de personal (feature 020). Sin PUT, PATCH ni DELETE: lo presentado, resuelto o aplicado no se
// edita ni se elimina. Las rutas estáticas van antes de `/{id}` para que el texto no se interprete como identificador.
Route::prefix('api/movimientos-personal')->middleware('api')->group(function () {
    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
        Route::get('/', [ActuacionController::class, 'index'])->name('movimientos.index');
        Route::get('/resumen', [ActuacionController::class, 'resumen'])->name('movimientos.resumen');
        Route::get('/contexto-de-movimiento', [ActuacionController::class, 'contextoDeMovimiento'])->name('movimientos.contexto');
        Route::get('/documentos', [ActuacionController::class, 'documentos'])->name('movimientos.documentos');

        Route::post('/novedades', [ActuacionController::class, 'novedad'])->defaults('modo', 'novedad_admin')->name('movimientos.novedades.store');
        Route::post('/ausencias', [ActuacionController::class, 'ausencia'])->defaults('modo', 'ausencia')->name('movimientos.ausencias.store');
        Route::post('/movimientos', [ActuacionController::class, 'movimiento'])->defaults('modo', 'movimiento')->name('movimientos.movimientos.store');

        Route::get('/{id}', [ActuacionController::class, 'show'])->whereNumber('id')->name('movimientos.show');
        Route::post('/{id}/revision', [ActuacionController::class, 'revision'])->whereNumber('id')->name('movimientos.revision');
        Route::post('/{id}/aprobacion', [ActuacionController::class, 'aprobacion'])->whereNumber('id')->name('movimientos.aprobacion');
        Route::post('/{id}/rechazo', [ActuacionController::class, 'rechazo'])->whereNumber('id')->name('movimientos.rechazo');
        Route::post('/{id}/retiro', [ActuacionController::class, 'retiro'])->whereNumber('id')->name('movimientos.retiro');
        Route::post('/{id}/revision-de-ausencia', [ActuacionController::class, 'revisionDeAusencia'])->whereNumber('id')->name('movimientos.revision-de-ausencia');
        Route::post('/{id}/aplicacion', [ActuacionController::class, 'aplicacion'])->whereNumber('id')->name('movimientos.aplicacion');
    });
});

// Solicitudes de la propia ficha: solo autenticación; el caso de uso exige la asociación cuenta–ficha.
Route::prefix('api/mis-solicitudes')->middleware('api')->group(function () {
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/', [MiSolicitudController::class, 'index'])->name('mis-solicitudes.index');
        Route::get('/documentos', [MiSolicitudController::class, 'documentos'])->name('mis-solicitudes.documentos');
        Route::post('/novedades', [MiSolicitudController::class, 'novedad'])->defaults('modo', 'novedad')->name('mis-solicitudes.novedades.store');
        Route::get('/{id}', [MiSolicitudController::class, 'show'])->whereNumber('id')->name('mis-solicitudes.show');
        Route::post('/{id}/retiro', [MiSolicitudController::class, 'retiro'])->whereNumber('id')->name('mis-solicitudes.retiro');
    });
});

// Offboarding de colaboradores (feature 021). Sin PUT, PATCH ni DELETE: lo formalizado, aplicado o respondido no se edita
// ni se elimina. Las rutas estáticas van antes de `/{id}` para que el texto no se interprete como identificador.
Route::prefix('api/offboarding')->middleware('api')->group(function () {
    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
        Route::get('/', [OffboardingController::class, 'index'])->name('offboarding.index');
        Route::get('/resumen', [OffboardingController::class, 'resumen'])->name('offboarding.resumen');
        Route::get('/contexto', [OffboardingController::class, 'contextoDeSalida'])->name('offboarding.contexto');
        Route::get('/documentos', [OffboardingController::class, 'documentos'])->name('offboarding.documentos');
        Route::get('/agregados', [OffboardingController::class, 'agregados'])->name('offboarding.agregados');
        Route::post('/', [OffboardingController::class, 'store'])->name('offboarding.store');

        Route::get('/{id}', [OffboardingController::class, 'show'])->whereNumber('id')->name('offboarding.show');
        Route::post('/{id}/cancelacion', [OffboardingController::class, 'cancelacion'])->whereNumber('id')->name('offboarding.cancelacion');
        Route::post('/{id}/tareas/{tarea}/completar', [OffboardingController::class, 'completarTarea'])->whereNumber('id')->whereNumber('tarea')->name('offboarding.tareas.completar');
        Route::post('/{id}/aplicacion', [OffboardingController::class, 'aplicacion'])->whereNumber('id')->name('offboarding.aplicacion');
        Route::post('/{id}/cierre', [OffboardingController::class, 'cierre'])->whereNumber('id')->name('offboarding.cierre');
        Route::post('/{id}/entrevista/no-realizada', [OffboardingController::class, 'entrevistaNoRealizada'])->whereNumber('id')->name('offboarding.entrevista.no-realizada');
        Route::get('/{id}/entrevista/respuestas', [OffboardingController::class, 'respuestas'])->whereNumber('id')->name('offboarding.entrevista.respuestas');
        Route::get('/{id}/entrevista/estructura', [OffboardingController::class, 'estructura'])->whereNumber('id')->name('offboarding.entrevista.estructura');
    });
});

// Desvinculación de la propia ficha: solo autenticación; el caso de uso exige la asociación cuenta–ficha.
Route::prefix('api/mi-desvinculacion')->middleware('api')->group(function () {
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/', [MiDesvinculacionController::class, 'show'])->name('mi-desvinculacion.show');
        Route::get('/entrevista', [MiDesvinculacionController::class, 'entrevista'])->name('mi-desvinculacion.entrevista');
        Route::put('/entrevista', [MiDesvinculacionController::class, 'guardar'])->name('mi-desvinculacion.entrevista.guardar');
        Route::post('/entrevista/confirmacion', [MiDesvinculacionController::class, 'confirmar'])->name('mi-desvinculacion.entrevista.confirmacion');
        Route::post('/entrevista/declinacion', [MiDesvinculacionController::class, 'declinar'])->name('mi-desvinculacion.entrevista.declinacion');
    });
});
