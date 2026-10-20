<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Ports\FuenteDePlanesInterface;
use App\Modules\Desarrollo\Application\Ports\RelojInterface;
use App\Modules\Desarrollo\Application\Services\ArmadorDeImpacto;
use App\Modules\Desarrollo\Domain\Repositories\ReportesRepositoryInterface;
use App\Modules\Desarrollo\Domain\Services\CalculadorDeRoi;

/** Acciones Finalizadas con su evaluación de impacto (si la tienen) y el ROI calculado de cada una. */
final class ListarImpactos
{
    public function __construct(
        private readonly FuenteDePlanesInterface $planes,
        private readonly ReportesRepositoryInterface $reportes,
        private readonly CalculadorDeRoi $roi,
        private readonly ArmadorDeImpacto $armador,
        private readonly RelojInterface $reloj,
    ) {}

    /**
     * @param  'con'|'sin'|null  $evaluado
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function ejecutar(int $tenantId, int $usuarioId, ?int $planId, ?string $evaluado, int $pagina, int $porPagina): array
    {
        return $this->paginar($this->todas($tenantId, $usuarioId, $planId, $evaluado), $pagina, $porPagina);
    }

    /**
     * Todas las filas sin paginar (también las usa la exportación).
     *
     * @param  'con'|'sin'|null  $evaluado
     * @return list<array<string, mixed>>
     */
    public function todas(int $tenantId, int $usuarioId, ?int $planId, ?string $evaluado = null): array
    {
        $acciones = array_values(array_filter(
            $this->planes->acciones($tenantId, $this->reloj->hoy($usuarioId)),
            fn (array $a) => $a['estado_accion'] === 'Finalizada' && ! $a['cancelada']
                && in_array($a['plan_estado'], ['Ejecutando', 'Cerrado'], true)
                && ($planId === null || $a['plan_id'] === $planId),
        ));
        $impactos = $this->reportes->impactosDe($tenantId, array_column($acciones, 'accion_id'));
        $nombres = $this->armador->nombresDe(array_values($impactos));

        $filas = [];
        foreach ($acciones as $a) {
            $impacto = $impactos[$a['accion_id']] ?? null;
            if (($evaluado === 'con' && $impacto === null) || ($evaluado === 'sin' && $impacto !== null)) {
                continue;
            }
            $roi = $impacto === null ? null : $this->roi->deAccion($impacto->beneficioEstimado, $a['costo']);
            $filas[] = [
                'plan_accion_id' => $a['accion_id'],
                'plan_id' => $a['plan_id'],
                'plan_codigo' => $a['plan_codigo'],
                'plan_nombre' => $a['plan_nombre'],
                'accion_nombre' => $a['nombre'],
                'tipo' => $a['tipo'],
                'fecha_fin' => $a['fecha_fin'],
                'costo' => $a['costo'],
                'evaluada' => $impacto !== null,
                'roi' => $roi === null ? null : round($roi, 1),
                'impacto' => $impacto === null ? null : $this->armador->armar($impacto, $a['costo'], $nombres),
            ];
        }
        usort($filas, fn (array $x, array $y) => [$y['fecha_fin'] ?? '', $y['plan_accion_id']] <=> [$x['fecha_fin'] ?? '', $x['plan_accion_id']]);

        return $filas;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    private function paginar(array $filas, int $pagina, int $porPagina): array
    {
        $pagina = max(1, $pagina);

        return ['items' => array_slice($filas, ($pagina - 1) * $porPagina, $porPagina), 'total' => count($filas)];
    }
}
