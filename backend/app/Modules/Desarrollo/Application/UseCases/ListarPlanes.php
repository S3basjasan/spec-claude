<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Services\ArmadorDePlanes;
use App\Modules\Desarrollo\Domain\Repositories\PlanRepositoryInterface;
use App\Modules\Desarrollo\Domain\ValueObjects\FiltroPlanes;

/** Tabla de planes: sin acciones, con cantidad y costo total de las no canceladas. */
final class ListarPlanes
{
    public function __construct(
        private readonly PlanRepositoryInterface $repositorio,
        private readonly ArmadorDePlanes $armador,
    ) {}

    /**
     * @return array{data: list<array<string, mixed>>, meta: array{page: int, per_page: int, total: int, last_page: int}}
     */
    public function ejecutar(int $tenantId, int $usuarioId, FiltroPlanes $filtro): array
    {
        $pagina = $this->repositorio->listar($tenantId, $filtro);

        return [
            'data' => array_map(fn (array $fila) => $this->armador->fila($fila, $usuarioId), $pagina['items']),
            'meta' => ['page' => $filtro->pagina, 'per_page' => $filtro->porPagina, 'total' => $pagina['total'], 'last_page' => max(1, (int) ceil($pagina['total'] / $filtro->porPagina))],
        ];
    }
}
