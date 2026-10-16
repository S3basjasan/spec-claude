<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Ports\NombresDeReferenciaInterface;
use App\Modules\Desarrollo\Application\Ports\RelojInterface;
use App\Modules\Desarrollo\Domain\Repositories\PlanRepositoryInterface;
use App\Modules\Desarrollo\Domain\Services\CalculadorDeEstadoDeAccion;
use App\Modules\Desarrollo\Domain\ValueObjects\FiltroAcciones;
use DateTimeImmutable;
use Throwable;

/**
 * Acciones de todos los planes del tenant para la Lista y el Calendario. Por defecto muestra las del año actual; si
 * se indica un plan o un rango de fechas no se añade ese filtro de año.
 */
final class ListarAccionesDePlanes
{
    public function __construct(
        private readonly PlanRepositoryInterface $repositorio,
        private readonly NombresDeReferenciaInterface $referencias,
        private readonly CalculadorDeEstadoDeAccion $calculador,
        private readonly RelojInterface $reloj,
    ) {}

    /**
     * @return array{data: list<array<string, mixed>>, meta: array{page: int, per_page: int, total: int, last_page: int}}
     */
    public function ejecutar(int $tenantId, int $usuarioId, FiltroAcciones $filtro): array
    {
        $hoy = $this->reloj->hoy($usuarioId);
        if ($filtro->anio === null && $filtro->planId === null && $filtro->desde === null && $filtro->hasta === null) {
            $filtro = new FiltroAcciones($filtro->estadoPlan, $filtro->tipo, $filtro->competenciaId, null, null, (int) $hoy->format('Y'), null, $filtro->pagina, $filtro->porPagina);
        }

        $pagina = $this->repositorio->listarAcciones($tenantId, $filtro);
        $competencias = $this->nombresDeCompetencias(array_values(array_unique(array_merge(...array_column($pagina['items'], 'competencia_ids') ?: [[]]))));

        return [
            'data' => array_map(fn (array $a) => $this->fila($a, $competencias, $hoy), $pagina['items']),
            'meta' => ['page' => $filtro->pagina, 'per_page' => $filtro->porPagina, 'total' => $pagina['total'], 'last_page' => max(1, (int) ceil($pagina['total'] / $filtro->porPagina))],
        ];
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  array<int, array{codigo: string, nombre: string, activa: bool}>  $competencias
     * @return array<string, mixed>
     */
    private function fila(array $a, array $competencias, DateTimeImmutable $hoy): array
    {
        return [
            'id' => $a['accion_id'],
            'plan' => ['id' => $a['plan_id'], 'codigo' => $a['plan_codigo'], 'nombre' => $a['plan_nombre'], 'estado' => $a['plan_estado']->value],
            'nombre' => $a['nombre'],
            'competencias' => array_map(fn (int $id) => ['id' => $id, 'nombre' => $competencias[$id]['nombre'] ?? 'No disponible'], $a['competencia_ids']),
            'tipo' => $a['tipo']?->value,
            'fecha_inicio' => $a['inicio']?->format('Y-m-d'),
            'fecha_fin' => $a['fin']?->format('Y-m-d'),
            'formador' => $a['formador'],
            'inscritos' => $a['inscritos'],
            'capacidad_maxima' => $a['capacidad_maxima'],
            'costo' => $a['costo'],
            'cancelada' => $a['cancelada'],
            'motivo_cancelacion' => $a['motivo_cancelacion'],
            'estado_accion' => $this->calculador->calcular($a['cancelada'], $a['inicio'], $a['fin'], $hoy)->value,
        ];
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, array{codigo: string, nombre: string, activa: bool}>
     */
    private function nombresDeCompetencias(array $ids): array
    {
        try {
            return $ids === [] ? [] : $this->referencias->competencias($ids);
        } catch (Throwable) {
            return [];
        }
    }
}
