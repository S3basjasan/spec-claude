<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Ports\RelojInterface;
use App\Modules\Desarrollo\Application\Services\AccesoAPdi;
use App\Modules\Desarrollo\Application\Services\ArmadorDeDetalleDePdi;
use App\Modules\Desarrollo\Domain\Entities\Pdi;
use App\Modules\Desarrollo\Domain\Repositories\RepositorioDePdiInterface;

/**
 * «Mi plan de desarrollo»: los planes del colaborador del usuario autenticado que ya tienen el acuerdo del responsable
 * (FR-047) y la sección «Qué sigue» con las próximas acciones e hitos de sus planes Acordados.
 */
final class ObtenerMiPlanDeDesarrollo
{
    public function __construct(
        private readonly AccesoAPdi $acceso,
        private readonly RepositorioDePdiInterface $repositorio,
        private readonly ArmadorDeDetalleDePdi $armador,
        private readonly RelojInterface $reloj,
    ) {}

    /**
     * @return array{planes: list<array<string, mixed>>, que_sigue: list<array<string, mixed>>}
     */
    public function ejecutar(int $tenantId, int $usuarioId): array
    {
        $colaboradorId = $this->acceso->colaboradorDe($usuarioId, $tenantId);
        if ($colaboradorId === null) {
            return ['planes' => [], 'que_sigue' => []];
        }

        $visibles = array_values(array_filter(
            $this->repositorio->deColaborador($tenantId, $colaboradorId),
            fn (Pdi $p) => $p->visibleParaColaborador(),
        ));
        $planes = array_map(fn (Pdi $p) => $this->armador->detalle($p, false, $usuarioId), $visibles);

        return ['planes' => $planes, 'que_sigue' => $this->queSigue($planes, $this->reloj->hoy($usuarioId)->format('Y-m-d'))];
    }

    /**
     * @param  list<array<string, mixed>>  $planes
     * @return list<array<string, mixed>>
     */
    private function queSigue(array $planes, string $hoy): array
    {
        $items = [];
        foreach ($planes as $plan) {
            if ($plan['estado'] !== 'Acordado') {
                continue;
            }
            foreach ($plan['compromisos'] as $compromiso) {
                foreach ($compromiso['acciones'] as $accion) {
                    if (in_array($accion['estado'], ['Pendiente', 'EnCurso'], true)) {
                        $items[] = [
                            'tipo' => 'accion',
                            'fecha' => $accion['fecha_fin'],
                            'descripcion' => $accion['descripcion'],
                            'plan_id' => $plan['id'],
                            'plan_codigo' => $plan['codigo'],
                            'tipo_plan' => $plan['tipo'],
                            'vencido' => $accion['fecha_fin'] < $hoy,
                            'actividad_id' => $accion['vinculo']['actividad_id'] ?? null,
                        ];
                    }
                }
            }
            foreach ($plan['hitos'] as $hito) {
                if ($hito['estado'] === 'Pendiente') {
                    $items[] = [
                        'tipo' => 'hito',
                        'fecha' => $hito['fecha'],
                        'descripcion' => $hito['descripcion'],
                        'plan_id' => $plan['id'],
                        'plan_codigo' => $plan['codigo'],
                        'tipo_plan' => $plan['tipo'],
                        'vencido' => $hito['vencido'],
                        'actividad_id' => null,
                    ];
                }
            }
        }

        usort($items, fn (array $a, array $b) => [$a['fecha'], $a['tipo']] <=> [$b['fecha'], $b['tipo']]);

        return $items;
    }
}
