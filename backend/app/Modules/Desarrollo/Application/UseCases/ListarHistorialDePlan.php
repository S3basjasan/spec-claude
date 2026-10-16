<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Ports\NombresDeUsuarioInterface;
use App\Modules\Desarrollo\Domain\Exceptions\PlanNoEncontrado;
use App\Modules\Desarrollo\Domain\Repositories\PlanRepositoryInterface;
use DateTimeImmutable;

/** Historial de un plan, el más reciente primero: cambios de estado y, desde la aprobación, participantes y cancelaciones. */
final class ListarHistorialDePlan
{
    public function __construct(
        private readonly PlanRepositoryInterface $repositorio,
        private readonly NombresDeUsuarioInterface $usuarios,
    ) {}

    /**
     * @return array{data: list<array<string, mixed>>, meta: array{page: int, per_page: int, total: int, last_page: int}}
     *
     * @throws PlanNoEncontrado
     */
    public function ejecutar(int $tenantId, int $planId, int $pagina, int $porPagina): array
    {
        if ($this->repositorio->obtener($tenantId, $planId) === null) {
            throw new PlanNoEncontrado;
        }

        $resultado = $this->repositorio->historial($tenantId, $planId, $pagina, $porPagina);
        $nombres = $this->usuarios->nombresDe(array_values(array_unique(array_column($resultado['items'], 'usuario_id'))));

        return [
            'data' => array_map(fn (array $r) => [
                'id' => $r['id'],
                'tipo' => $r['tipo']->value,
                'accion_id' => $r['accion_id'],
                'accion_nombre' => $r['accion_nombre'],
                'valor_anterior' => $r['valor_anterior'],
                'valor_nuevo' => $r['valor_nuevo'],
                'motivo' => $r['motivo'],
                'usuario' => ['id' => $r['usuario_id'], 'nombre' => $nombres[$r['usuario_id']] ?? 'No disponible'],
                'fecha' => $r['fecha'] instanceof DateTimeImmutable ? $r['fecha']->format(DATE_ATOM) : null,
            ], $resultado['items']),
            'meta' => ['page' => $pagina, 'per_page' => $porPagina, 'total' => $resultado['total'], 'last_page' => max(1, (int) ceil($resultado['total'] / $porPagina))],
        ];
    }
}
