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
