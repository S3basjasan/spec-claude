<?php

use App\Modules\Desarrollo\Presentation\Controllers\ActividadAsistenciaController;
use App\Modules\Desarrollo\Presentation\Controllers\ActividadController;
use App\Modules\Desarrollo\Presentation\Controllers\AdecuacionesController;
use App\Modules\Desarrollo\Presentation\Controllers\AreaFormativaController;
use App\Modules\Desarrollo\Presentation\Controllers\CatalogoFormativoController;
use App\Modules\Desarrollo\Presentation\Controllers\CertificadoMicroController;
use App\Modules\Desarrollo\Presentation\Controllers\DnfController;
use App\Modules\Desarrollo\Presentation\Controllers\InscripcionesRutaController;
use App\Modules\Desarrollo\Presentation\Controllers\IntentoMicroController;
use App\Modules\Desarrollo\Presentation\Controllers\MiAdecuacionController;
use App\Modules\Desarrollo\Presentation\Controllers\MiAprendizajeController;
use App\Modules\Desarrollo\Presentation\Controllers\MicrocontenidoController;
use App\Modules\Desarrollo\Presentation\Controllers\MiProgresoController;
use App\Modules\Desarrollo\Presentation\Controllers\MiRutaController;
use App\Modules\Desarrollo\Presentation\Controllers\MisActividadesController;
use App\Modules\Desarrollo\Presentation\Controllers\PlanAccionController;
use App\Modules\Desarrollo\Presentation\Controllers\PlanController;
use App\Modules\Desarrollo\Presentation\Controllers\ProgresoMicroController;
use App\Modules\Desarrollo\Presentation\Controllers\ReportesController;
use App\Modules\Desarrollo\Presentation\Controllers\ReportesImpactoController;
use App\Modules\Desarrollo\Presentation\Controllers\RutasController;
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

// Plan de formación anual (feature 013). Prefijos /api/planes y /api/plan-acciones por contrato.
// No existe DELETE: los planes no se eliminan y las acciones solo se quitan de un Borrador mediante PUT.
// Las rutas fijas (`mi-plan`, `opciones`, `colaboradores`, `cursos`) se declaran antes de cualquier `/{id}`.
Route::prefix('api/planes')->middleware('api')->group(function () {
    // Plan propio del colaborador: solo autenticación; los datos son siempre los del usuario del token.
    Route::get('/mi-plan', [PlanController::class, 'miPlan'])->middleware('auth:sanctum')->name('planes.mi-plan');

    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
        Route::get('/opciones', [PlanController::class, 'opciones'])->name('planes.opciones');
        Route::get('/colaboradores', [PlanController::class, 'colaboradores'])->name('planes.colaboradores');
        Route::get('/cursos', [PlanController::class, 'cursos'])->name('planes.cursos');
        Route::get('/', [PlanController::class, 'index'])->name('planes.index');
        Route::post('/', [PlanController::class, 'store'])->name('planes.store');
        Route::get('/{id}', [PlanController::class, 'show'])->whereNumber('id')->name('planes.show');
        Route::put('/{id}', [PlanController::class, 'update'])->whereNumber('id')->name('planes.update');
        Route::get('/{id}/historial', [PlanController::class, 'historial'])->whereNumber('id')->name('planes.historial');
        Route::patch('/{id}/estado', [PlanController::class, 'estado'])->whereNumber('id')->name('planes.estado');
        Route::patch('/{id}/aprobador', [PlanController::class, 'aprobador'])->whereNumber('id')->name('planes.aprobador');
    });
});

Route::prefix('api/plan-acciones')->middleware(['api', 'auth:sanctum', 'admin'])->group(function () {
    Route::get('/', [PlanAccionController::class, 'index'])->name('plan-acciones.index');
    Route::put('/{id}/participantes', [PlanAccionController::class, 'participantes'])->whereNumber('id')->name('plan-acciones.participantes');
    Route::patch('/{id}/cancelar', [PlanAccionController::class, 'cancelar'])->whereNumber('id')->name('plan-acciones.cancelar');
});

// Actividades formativas (feature 017). Prefijo /api/actividades por contrato.
// No existe DELETE: las actividades y su historial nunca se eliminan. Las rutas fijas
// (`acciones-disponibles`, `departamentos`, `opciones`, `calificaciones`, `mis-actividades`) se declaran antes de cualquier `/{id}`.
Route::prefix('api/actividades')->middleware('api')->group(function () {
    // Actividades propias del colaborador: solo autenticación; los datos son siempre los del usuario del token.
    Route::get('/mis-actividades', [MisActividadesController::class, 'show'])->middleware('auth:sanctum')->name('actividades.mis-actividades');

    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
        Route::get('/acciones-disponibles', [ActividadController::class, 'accionesDisponibles'])->name('actividades.acciones-disponibles');
        Route::get('/departamentos', [ActividadController::class, 'departamentos'])->name('actividades.departamentos');
        Route::get('/opciones', [ActividadController::class, 'opciones'])->name('actividades.opciones');
        Route::get('/calificaciones', [ActividadController::class, 'calificaciones'])->name('actividades.calificaciones');
        Route::get('/', [ActividadController::class, 'index'])->name('actividades.index');
        Route::post('/', [ActividadController::class, 'store'])->name('actividades.store');
        Route::get('/{id}', [ActividadController::class, 'show'])->whereNumber('id')->name('actividades.show');
        Route::put('/{id}', [ActividadController::class, 'update'])->whereNumber('id')->name('actividades.update');
        Route::put('/{id}/asistencia', [ActividadAsistenciaController::class, 'update'])->whereNumber('id')->name('actividades.asistencia');
        Route::get('/{id}/historial', [ActividadController::class, 'historial'])->whereNumber('id')->name('actividades.historial');
    });
});

// Micro-learning (feature 016). Prefijo /api/microcontenidos por contrato.
// No existen DELETE de progreso ni PUT de historial: el progreso y el historial de estados solo se agregan.
// Las rutas fijas (`opciones`, `mi-aprendizaje`, `intentos/...`) se declaran antes de cualquier `/{id}`.
Route::prefix('api/microcontenidos')->middleware('api')->group(function () {
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/', [MicrocontenidoController::class, 'index'])->name('microcontenidos.index');
        Route::get('/mi-aprendizaje', [MiAprendizajeController::class, 'show'])->name('microcontenidos.mi-aprendizaje');
        Route::post('/intentos/{intentoId}/respuestas', [IntentoMicroController::class, 'responderPregunta'])->whereNumber('intentoId')->name('microcontenidos.responder');
    });

    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
        Route::get('/opciones', [MicrocontenidoController::class, 'opciones'])->name('microcontenidos.opciones');
        Route::post('/', [MicrocontenidoController::class, 'store'])->name('microcontenidos.store');
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/{id}', [MicrocontenidoController::class, 'show'])->whereNumber('id')->name('microcontenidos.show');
        Route::put('/{id}/progreso', [ProgresoMicroController::class, 'avance'])->whereNumber('id')->name('microcontenidos.avance');
        Route::post('/{id}/completar', [ProgresoMicroController::class, 'completar'])->whereNumber('id')->name('microcontenidos.completar');
        Route::post('/{id}/intentos', [IntentoMicroController::class, 'iniciar'])->whereNumber('id')->name('microcontenidos.intentos');
        Route::get('/{id}/certificado', [CertificadoMicroController::class, 'show'])->whereNumber('id')->name('microcontenidos.certificado');
    });

    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
        Route::put('/{id}', [MicrocontenidoController::class, 'update'])->whereNumber('id')->name('microcontenidos.update');
        Route::patch('/{id}/estado', [MicrocontenidoController::class, 'estado'])->whereNumber('id')->name('microcontenidos.estado');
        Route::delete('/{id}', [MicrocontenidoController::class, 'destroy'])->whereNumber('id')->name('microcontenidos.destroy');
        Route::get('/{id}/historial', [MicrocontenidoController::class, 'historial'])->whereNumber('id')->name('microcontenidos.historial');
    });
});

// Reportes de evaluación de resultados (feature 018). Prefijo /api/reportes por contrato.
// No existen PUT ni DELETE sobre encuestas ni seguimientos: son inmutables. Las rutas fijas se declaran antes de cualquier `/{id}`.
// Las métricas se calculan bajo demanda: solo se guardan encuestas, seguimientos y evaluaciones de impacto.
Route::prefix('api/reportes')->middleware('api')->group(function () {
    // Del colaborador: solo autenticación; los datos son siempre los del usuario del token.
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/mi-progreso', [MiProgresoController::class, 'show'])->name('reportes.mi-progreso');
        Route::post('/encuestas', [MiProgresoController::class, 'responderEncuesta'])->name('reportes.encuestas.store');
        Route::post('/seguimientos', [MiProgresoController::class, 'responderSeguimiento'])->name('reportes.seguimientos.store');
    });

    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
        Route::get('/opciones', [ReportesController::class, 'opciones'])->name('reportes.opciones');
        Route::get('/dashboard', [ReportesController::class, 'dashboard'])->name('reportes.dashboard');
        Route::get('/detalle', [ReportesController::class, 'detalle'])->name('reportes.detalle');
        Route::get('/actividades/{actividadId}/satisfaccion', [ReportesController::class, 'satisfaccion'])->whereNumber('actividadId')->name('reportes.satisfaccion');
        Route::get('/seguimiento', [ReportesController::class, 'seguimiento'])->name('reportes.seguimiento');
        Route::get('/exportar', [ReportesController::class, 'exportar'])->name('reportes.exportar');
        Route::get('/impactos', [ReportesImpactoController::class, 'index'])->name('reportes.impactos.index');
        Route::put('/impactos/{planAccionId}', [ReportesImpactoController::class, 'update'])->whereNumber('planAccionId')->name('reportes.impactos.update');
    });
});

// Rutas y planes de carrera (feature 019). Prefijo /api/rutas por contrato. Cada operación se autoriza con una clave de
// permiso (`permiso:<clave>`). No existen endpoints DELETE: las rutas se archivan y las inscripciones se retiran.
// Las rutas fijas (`permisos`, `opciones`, `mi-ruta`) se declaran antes de `/{id}` para que nunca se interpreten como identificador.
Route::prefix('api/rutas')->middleware(['api', 'auth:sanctum'])->group(function () {
    Route::get('/permisos', [RutasController::class, 'permisos'])->name('rutas.permisos');
    Route::get('/mi-ruta', [MiRutaController::class, 'show'])->middleware('permiso:desarrollo.rutas.ver_propio')->name('rutas.mi-ruta');
    Route::get('/opciones', [RutasController::class, 'opciones'])->middleware('permiso:desarrollo.rutas.gestionar')->name('rutas.opciones');
    Route::get('/', [RutasController::class, 'index'])->middleware('permiso:desarrollo.rutas.ver')->name('rutas.index');
    Route::post('/', [RutasController::class, 'store'])->middleware('permiso:desarrollo.rutas.gestionar')->name('rutas.store');
    Route::get('/{id}', [RutasController::class, 'show'])->whereNumber('id')->middleware('permiso:desarrollo.rutas.ver')->name('rutas.show');
    Route::put('/{id}', [RutasController::class, 'update'])->whereNumber('id')->middleware('permiso:desarrollo.rutas.gestionar')->name('rutas.update');
    Route::get('/{id}/historial', [RutasController::class, 'historial'])->whereNumber('id')->middleware('permiso:desarrollo.rutas.historial')->name('rutas.historial');
    Route::patch('/{id}/estado', [RutasController::class, 'cambiarEstado'])->whereNumber('id')->middleware('permiso:desarrollo.rutas.gestionar')->name('rutas.estado');
});

// Inscripciones a rutas de carrera (feature 019). Prefijo /api/inscripciones-ruta por contrato. Sin DELETE: se finalizan o se retiran.
Route::prefix('api/inscripciones-ruta')->middleware(['api', 'auth:sanctum'])->group(function () {
    Route::get('/', [InscripcionesRutaController::class, 'index'])->middleware('permiso:desarrollo.rutas.inscripciones')->name('inscripciones-ruta.index');
    Route::get('/colaboradores', [InscripcionesRutaController::class, 'colaboradores'])->middleware('permiso:desarrollo.rutas.inscribir')->name('inscripciones-ruta.colaboradores');
    Route::post('/', [InscripcionesRutaController::class, 'store'])->middleware('permiso:desarrollo.rutas.inscribir')->name('inscripciones-ruta.store');
    Route::patch('/{id}/niveles', [InscripcionesRutaController::class, 'niveles'])->whereNumber('id')->middleware('permiso:desarrollo.rutas.inscribir')->name('inscripciones-ruta.niveles');
    Route::patch('/{id}/alcanzar', [InscripcionesRutaController::class, 'alcanzar'])->whereNumber('id')->middleware('permiso:desarrollo.rutas.inscribir')->name('inscripciones-ruta.alcanzar');
    Route::patch('/{id}/retirar', [InscripcionesRutaController::class, 'retirar'])->whereNumber('id')->middleware('permiso:desarrollo.rutas.inscribir')->name('inscripciones-ruta.retirar');
});

// Adecuación a puesto futuro y mapa de talento (feature 020). Prefijo /api/adecuaciones por contrato (desviación aprobada).
// Cada operación se autoriza con una clave de permiso (`permiso:<clave>`). No existen endpoints PUT, PATCH ni DELETE sobre
// las adecuaciones: son inmutables. Las rutas fijas se declaran antes de `/{id}` para que nunca se interpreten como identificador.
Route::prefix('api/adecuaciones')->middleware(['api', 'auth:sanctum'])->group(function () {
    Route::get('/permisos', [AdecuacionesController::class, 'permisos'])->name('adecuaciones.permisos');
    Route::get('/opciones', [AdecuacionesController::class, 'opciones'])->middleware('permiso:desarrollo.adecuacion.generar')->name('adecuaciones.opciones');
    Route::get('/mi-adecuacion', [MiAdecuacionController::class, 'show'])->middleware('permiso:desarrollo.adecuacion.ver_propio')->name('adecuaciones.mi-adecuacion');
    Route::post('/previsualizar', [AdecuacionesController::class, 'previsualizar'])->middleware('permiso:desarrollo.adecuacion.generar')->name('adecuaciones.previsualizar');
    Route::post('/', [AdecuacionesController::class, 'store'])->middleware('permiso:desarrollo.adecuacion.generar')->name('adecuaciones.store');
    Route::get('/parametros', [AdecuacionesController::class, 'parametros'])->middleware('permiso:desarrollo.adecuacion.parametros')->name('adecuaciones.parametros');
    Route::put('/parametros', [AdecuacionesController::class, 'actualizarParametros'])->middleware('permiso:desarrollo.adecuacion.parametros')->name('adecuaciones.parametros.actualizar');
    Route::get('/mapa', [AdecuacionesController::class, 'mapa'])->middleware('permiso:desarrollo.adecuacion.mapa')->name('adecuaciones.mapa');
    Route::get('/comparar', [AdecuacionesController::class, 'comparar'])->middleware('permiso:desarrollo.adecuacion.comparar')->name('adecuaciones.comparar');
    Route::get('/', [AdecuacionesController::class, 'index'])->middleware('permiso:desarrollo.adecuacion.ver')->name('adecuaciones.index');
    Route::get('/{id}', [AdecuacionesController::class, 'show'])->whereNumber('id')->middleware('permiso:desarrollo.adecuacion.ver')->name('adecuaciones.show');
});
