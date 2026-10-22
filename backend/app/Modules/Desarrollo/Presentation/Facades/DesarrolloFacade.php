<?php

namespace App\Modules\Desarrollo\Presentation\Facades;

use App\Modules\Desarrollo\Application\Services\DesarrolloService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static array getOfertaPorCompetencia(int $competenciaId) Cursos y micro-contenidos disponibles de la competencia.
 * @method static bool competenciaTieneOferta(int $competenciaId) Indica si tiene al menos un curso o micro-contenido disponible.
 * @method static array brechasActivas(int $tenantId, int $colaboradorId) Brechas «Con brecha» de la DNF más reciente del colaborador: [{competencia_id, nivel_requerido, nivel_actual, diferencia, prioridad}].
 * @method static array getDnfRegistradas(int $tenantId, array $filtros = []) Cabeceras de las DNF registradas del tenant (sin detalle de brechas), para el indicador de brechas cerradas de Reportes.
 * @method static int crearBorradorDesdeDnf(int $tenantId, int $actorId, int $dnfId, array $brechas) Crea un plan en Borrador del año actual con una acción por competencia con brecha y los colaboradores con brecha como participantes; devuelve el id del plan.
 * @method static array getAccionesDePlanesEjecutando(int $tenantId) Acciones no canceladas de planes Ejecutando con sus participantes: [{accion_id, plan_id, nombre, tipo, fecha_inicio, fecha_fin, participantes}].
 * @method static array getPlanesYAcciones(int $tenantId, array $filtros = []) Planes con sus acciones, horas, costos y estados (filtros: anio, estado, plan_id).
 * @method static array getMicrocontenidosPublicadosPorCompetencia(int $competenciaId) Micro-contenidos Publicados de la competencia: [{id, codigo, titulo, tipo, duracion_minutos, miniatura_url, url_destino}].
 * @method static array getResumenAprendizaje(int $tenantId, int $colaboradorId) Resumen del colaborador: {completados, en_progreso, horas_acumuladas, certificados}.
 * @method static array getCompletaciones(int $tenantId, array $filtros = []) Completaciones de micro-contenidos del tenant (filtros: colaborador_id, microcontenido_id, desde, hasta).
 * @method static array getAsistenciaDeColaborador(int $tenantId, int $colaboradorId, array $accionIds) Asistencia y calificación del colaborador en las actividades de esas acciones: [{accion_id, actividad_id, estado, asistencia, calificacion}] (Pendiente si no hay registro; sin observaciones).
 * @method static bool tieneAsistenciaRegistrada(int $tenantId, int $accionId, int $colaboradorId) Indica si el colaborador ya tiene asistencia registrada en la actividad de la acción.
 * @method static array getActividades(int $tenantId, array $filtros = []) Actividades formativas con estado calculado, horas, inscritos y promedio (filtros: anio, plan_id, tipo, estado, departamento_id, competencia_id, fecha_desde, fecha_hasta).
 * @method static array getAsistencias(int $tenantId, array $filtros = []) Registros de asistencia y calificación sin observaciones (filtros de getActividades más colaborador_id y actividad_id).
 * @method static array inscripcionesActivas(int $tenantId, int $colaboradorId) Inscripciones Activas del colaborador a rutas de carrera: [{inscripcion_id, ruta_id, ruta_codigo, ruta_nombre, ruta_tipo, nivel_actual: {id, orden, puesto_id, nombre}|null, nivel_objetivo: {id, orden, puesto_id, nombre, experiencia_minima_anios}|null}]. Las de quien ya no tiene vínculo vigente se retiran y no se devuelven.
 * @method static array|null obtenerNivel(int $tenantId, int $nivelId) Nivel de una ruta: {nivel_id, ruta_id, ruta_nombre, orden, puesto_id, nombre, experiencia_minima_anios}, o null.
 * @method static array|null ultimaAdecuacion(int $tenantId, int $colaboradorId, int $puestoObjetivoId) Adecuación vigente del colaborador para ese puesto objetivo: {adecuacion_id, codigo, fecha, preparacion, umbral_meses, puesto_actual_id, puesto_objetivo_id, inscripcion_id, brechas: [{dimension, tipo_elemento, elemento_id, nombre, diferencia, tiempo_cierre_meses}]}, o null.
 * @method static array adecuacionesPorFiltros(int $tenantId, array $filtros = []) Cabeceras de adecuaciones vigentes [{adecuacion_id, colaborador_id, puesto_objetivo_id, preparacion, brechas, tiempo_maximo_cierre_meses, fecha}] (filtros: fecha_desde, fecha_hasta, colaborador_id, puesto_objetivo_id, preparacion, departamento_id).
 *
 * @see DesarrolloService
 */
class DesarrolloFacade extends Facade
{
    /**
     * Obtiene el nombre registrado del componente en el Service Container.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'desarrollo-facade';
    }
}
