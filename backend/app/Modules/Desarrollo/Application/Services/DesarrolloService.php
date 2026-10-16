<?php

namespace App\Modules\Desarrollo\Application\Services;

use App\Modules\Desarrollo\Application\UseCases\CrearBorradorDesdeDnf;
use App\Modules\Desarrollo\Application\UseCases\ObtenerBrechasActivas;
use App\Modules\Desarrollo\Domain\Repositories\CatalogoFormativoRepositoryInterface;
use App\Modules\Desarrollo\Domain\Repositories\DnfRepositoryInterface;
use App\Modules\Desarrollo\Domain\Repositories\PlanRepositoryInterface;
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
}
