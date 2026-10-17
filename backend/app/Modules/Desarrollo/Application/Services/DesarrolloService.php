<?php

namespace App\Modules\Desarrollo\Application\Services;

use App\Modules\Desarrollo\Application\UseCases\ConsultarActividadesParaOtrosModulos;
use App\Modules\Desarrollo\Application\UseCases\CrearBorradorDesdeDnf;
use App\Modules\Desarrollo\Application\UseCases\ObtenerBrechasActivas;
use App\Modules\Desarrollo\Domain\Repositories\CatalogoFormativoRepositoryInterface;
use App\Modules\Desarrollo\Domain\Repositories\DnfRepositoryInterface;
use App\Modules\Desarrollo\Domain\Repositories\MicrocontenidoRepositoryInterface;
use App\Modules\Desarrollo\Domain\Repositories\PlanRepositoryInterface;
use App\Modules\Desarrollo\Domain\Repositories\ProgresoMicroRepositoryInterface;
use App\Modules\Desarrollo\Domain\Services\CalculadorDeResumen;
use App\Modules\Desarrollo\Domain\ValueObjects\FiltroDnf;
use App\Modules\Desarrollo\Domain\ValueObjects\PrioridadBrecha;
use App\Modules\Desarrollo\Domain\ValueObjects\RecursoFormativo;

/**
 * Servicio inter-módulos del módulo Desarrollo (detrás de DesarrolloFacade). Solo lectura: ningún módulo
 * consumidor puede modificar asociaciones. Ver specs/005-catalogo-formativo/contracts/desarrollo-puertos.md. La única
 * escritura es `crearBorradorDesdeDnf` (feature 013), que solo crea un plan en Borrador; ver
 * specs/013-plan-formacion-anual/contracts/plan-puertos-y-facades.md.
 */
class DesarrolloService
{
    public function __construct(
        private readonly CatalogoFormativoRepositoryInterface $repositorio,
        private readonly ObtenerBrechasActivas $brechasActivas,
        private readonly DnfRepositoryInterface $dnf,
        private readonly PlanRepositoryInterface $planes,
        private readonly CrearBorradorDesdeDnf $crearBorrador,
        private readonly MicrocontenidoRepositoryInterface $microcontenidos,
        private readonly ProgresoMicroRepositoryInterface $progresosMicro,
        private readonly CalculadorDeResumen $calculadorDeResumen,
        private readonly ConsultarActividadesParaOtrosModulos $actividades,
    ) {}

    /**
     * Crea un plan en Borrador del año actual a partir de las brechas de una DNF (una acción por competencia con
     * brecha y los colaboradores con brecha como participantes). Devuelve el id del plan.
     *
     * @param  list<array{colaborador_id: int, competencia_id: int}>  $brechas
     */
    public function crearBorradorDesdeDnf(int $tenantId, int $actorId, int $dnfId, array $brechas): int
    {
        return $this->crearBorrador->ejecutar($tenantId, $actorId, $dnfId, $brechas);
    }

    /**
     * Acciones no canceladas de los planes en Ejecutando con sus participantes (para Actividades Formativas). Solo lectura.
     *
     * @return list<array{accion_id: int, plan_id: int, nombre: string, tipo: string|null, fecha_inicio: string|null, fecha_fin: string|null, participantes: list<int>}>
     */
    public function getAccionesDePlanesEjecutando(int $tenantId): array
    {
        return $this->planes->accionesDePlanesEjecutando($tenantId);
    }

    /**
     * Planes con sus acciones, horas, costos y estados (para Reportes). Filtros opcionales: anio, estado, plan_id. Solo lectura.
     *
     * @param  array{anio?: int, estado?: string, plan_id?: int}  $filtros
     * @return list<array<string, mixed>>
     */
    public function getPlanesYAcciones(int $tenantId, array $filtros = []): array
    {
        return $this->planes->planesConAcciones($tenantId, $filtros);
    }

    /**
     * Oferta formativa disponible de una competencia (cursos y micro-contenidos no archivados).
     *
     * @return array{competencia_id: int, area_formativa: array{id: int, nombre: string}|null, cursos: array<int, array<string, mixed>>, microcontenidos: array<int, array<string, mixed>>}
     */
    public function getOfertaPorCompetencia(int $competenciaId): array
    {
        $config = $this->repositorio->configuracionDe($competenciaId);
        $aArreglo = fn (RecursoFormativo $r) => [
            'id' => $r->id,
            'nombre' => $r->nombre,
            'tipo' => $r->tipo,
            'duracion_minutos' => $r->duracionMinutos,
            'formador' => $r->formador,
            'url_destino' => $r->urlDestino,
        ];

        return [
            'competencia_id' => $competenciaId,
            'area_formativa' => $config->areaFormativaId === null ? null : ['id' => $config->areaFormativaId, 'nombre' => (string) $config->areaNombre],
            'cursos' => array_map($aArreglo, $config->cursosDisponibles()),
            'microcontenidos' => array_map($aArreglo, $config->microcontenidosDisponibles()),
        ];
    }

    public function competenciaTieneOferta(int $competenciaId): bool
    {
        return $this->repositorio->configuracionDe($competenciaId)->recursosDisponibles() >= 1;
    }

    /**
     * Brechas activas de un colaborador: las «Con brecha» de su DNF más reciente (individual o masiva). Solo lectura.
     * El tenant es explícito para que la Facade no dependa del contexto de la petición (p. ej. Reportes).
     *
     * @return list<array{competencia_id: int, nivel_requerido: string, nivel_actual: string|null, diferencia: int|null, prioridad: string|null}>
     */
    public function brechasActivas(int $tenantId, int $colaboradorId): array
    {
        return $this->brechasActivas->ejecutar($tenantId, $colaboradorId);
    }

    /**
     * Cabeceras de las DNF registradas del tenant (sin detalle de brechas), para indicadores como el de brechas
     * cerradas de Reportes. Filtros opcionales: fecha_desde, fecha_hasta (AAAA-MM-DD), colaborador_id, departamento_id y
     * prioridad. Solo lectura: no existe forma de modificar una DNF.
     *
     * @param  array{fecha_desde?: string, fecha_hasta?: string, colaborador_id?: int, departamento_id?: int, prioridad?: string}  $filtros
     * @return list<array{id: int, codigo: string, fecha: string, alcance: string, colaborador_id: int|null, departamento_id: int|null, colaboradores: int, cantidad_brechas: int, prioridad_mas_alta: string|null}>
     */
    public function getDnfRegistradas(int $tenantId, array $filtros = []): array
    {
        $registradas = [];
        $pagina = 1;
        do {
            $resultado = $this->dnf->listar($tenantId, new FiltroDnf(
                $filtros['fecha_desde'] ?? null,
                $filtros['fecha_hasta'] ?? null,
                $filtros['colaborador_id'] ?? null,
                $filtros['departamento_id'] ?? null,
                isset($filtros['prioridad']) ? PrioridadBrecha::tryFrom($filtros['prioridad']) : null,
                $pagina,
                100,
            ));
            foreach ($resultado->items as $r) {
                $registradas[] = [
                    'id' => $r->id,
                    'codigo' => $r->codigo,
                    'fecha' => $r->fecha->format(DATE_ATOM),
                    'alcance' => $r->alcance->value,
                    'colaborador_id' => $r->colaboradorId,
                    'departamento_id' => $r->departamentoId,
                    'colaboradores' => $r->colaboradores,
                    'cantidad_brechas' => $r->cantidadBrechas,
                    'prioridad_mas_alta' => $r->prioridadMasAlta?->value,
                ];
            }
            $pagina++;
        } while ($pagina <= $resultado->ultimaPagina());

        return $registradas;
    }

    /**
     * Micro-contenidos Publicados vinculados a la competencia (micro-learning). Solo lectura.
     *
     * @return list<array{id: int, codigo: string, titulo: string, tipo: string, duracion_minutos: int, miniatura_url: string|null, url_destino: string}>
     */
    public function getMicrocontenidosPublicadosPorCompetencia(int $competenciaId): array
    {
        return array_values(array_map(fn ($m) => [
            'id' => $m->id,
            'codigo' => $m->codigo,
            'titulo' => $m->titulo,
            'tipo' => $m->tipo->value,
            'duracion_minutos' => $m->duracionMinutos,
            'miniatura_url' => $m->miniaturaUrl,
            'url_destino' => '/micro-learning/'.$m->id,
        ], $this->microcontenidos->publicadosDeCompetencia($competenciaId)));
    }

    /**
     * Resumen de aprendizaje de un colaborador para «Mi progreso» de Reportes. Solo lectura. Horas = suma de la duración
     * de los micro-contenidos completados dividida entre 60, con un decimal.
     *
     * @return array{completados: int, en_progreso: int, horas_acumuladas: float, certificados: int}
     */
    public function getResumenAprendizaje(int $tenantId, int $colaboradorId): array
    {
        $r = $this->progresosMicro->resumenDe($tenantId, $colaboradorId);

        return $this->calculadorDeResumen->resumir($r['completados'], $r['en_progreso'], $r['minutos']);
    }

    /**
     * Completaciones de micro-contenidos del tenant para los indicadores de Reportes. Solo lectura. Filtros opcionales:
     * colaborador_id, microcontenido_id, desde y hasta (AAAA-MM-DD, sobre la fecha de finalización).
     *
     * @param  array{colaborador_id?: int, microcontenido_id?: int, desde?: string, hasta?: string}  $filtros
     * @return list<array{microcontenido_id: int, codigo: string, titulo: string, tipo: string, duracion_minutos: int, colaborador_id: int, fecha_inicio: string, fecha_completado: string}>
     */
    public function getCompletaciones(int $tenantId, array $filtros = []): array
    {
        return array_values($this->progresosMicro->completaciones($tenantId, $filtros));
    }

    /**
     * Asistencia y calificación del colaborador en las actividades de las acciones indicadas (solo lectura; sin observaciones).
     * La asistencia sin registro se reporta Pendiente.
     *
     * @param  list<int>  $accionIds
     * @return list<array{accion_id: int, actividad_id: int, estado: string, asistencia: string, calificacion: int|null}>
     */
    public function getAsistenciaDeColaborador(int $tenantId, int $colaboradorId, array $accionIds): array
    {
        return $this->actividades->asistenciaDeColaborador($tenantId, $colaboradorId, $accionIds);
    }

    /** Indica si el colaborador ya tiene asistencia registrada en la actividad de la acción (para impedir quitarlo de la acción). */
    public function tieneAsistenciaRegistrada(int $tenantId, int $accionId, int $colaboradorId): bool
    {
        return $this->actividades->tieneAsistenciaRegistrada($tenantId, $accionId, $colaboradorId);
    }

    /**
     * Actividades formativas del tenant con su estado calculado, horas y promedio (para Reportes). Solo lectura.
     *
     * @param  array{anio?: int, plan_id?: int, tipo?: string, estado?: string, departamento_id?: int, competencia_id?: int, fecha_desde?: string, fecha_hasta?: string}  $filtros
     * @return list<array<string, mixed>>
     */
    public function getActividades(int $tenantId, array $filtros = []): array
    {
        return $this->actividades->actividades($tenantId, $filtros);
    }

    /**
     * Registros de asistencia y calificación (sin observaciones) de las actividades del tenant (para Reportes). Solo lectura.
     *
     * @param  array{anio?: int, plan_id?: int, tipo?: string, estado?: string, departamento_id?: int, competencia_id?: int, fecha_desde?: string, fecha_hasta?: string, colaborador_id?: int, actividad_id?: int}  $filtros
     * @return list<array<string, mixed>>
     */
    public function getAsistencias(int $tenantId, array $filtros = []): array
    {
        return $this->actividades->asistencias($tenantId, $filtros);
    }
}
