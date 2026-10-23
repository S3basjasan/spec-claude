<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Ports\DepartamentoDeColaboradorInterface;
use App\Modules\Desarrollo\Application\Ports\RelojInterface;
use App\Modules\Desarrollo\Application\Services\ArmadorDeResumenesDePdi;
use App\Modules\Desarrollo\Domain\Entities\Pdi;
use App\Modules\Desarrollo\Domain\Repositories\RepositorioDePdiInterface;
use Throwable;

/**
 * Lista de planes del administrador: más reciente primero, filtros por tipo, estado, colaborador, departamento,
 * responsable y rango de fechas de fin, con los indicadores del conjunto.
 */
final class ListarPdi
{
    public function __construct(
        private readonly RepositorioDePdiInterface $repositorio,
        private readonly DepartamentoDeColaboradorInterface $departamentoDe,
        private readonly ArmadorDeResumenesDePdi $armador,
        private readonly RelojInterface $reloj,
    ) {}

    /**
     * @param  array<string, mixed>  $filtros  tipo, estado, colaborador_id, departamento_id, responsable_id, fecha_desde, fecha_hasta
     * @return array{data: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function ejecutar(int $tenantId, int $usuarioId, array $filtros, int $pagina, int $porPagina): array
    {
        $hoy = $this->reloj->hoy($usuarioId)->format('Y-m-d');
        $planes = $this->repositorio->listar($tenantId, $filtros);

        if (! empty($filtros['departamento_id'])) {
            $planes = $this->deDepartamento($planes, $tenantId, (int) $filtros['departamento_id'], $hoy);
        }

        $total = count($planes);
        $pagina = max(1, $pagina);
        $slice = array_slice($planes, ($pagina - 1) * $porPagina, $porPagina);

        return [
            'data' => $this->armador->resumenes($slice, $tenantId, $usuarioId),
            'meta' => [
                'page' => $pagina,
                'per_page' => $porPagina,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $porPagina)),
                'kpis' => $this->repositorio->indicadores($tenantId, $hoy),
            ],
        ];
    }

    /**
     * @param  list<Pdi>  $planes
     * @return list<Pdi>
     */
    private function deDepartamento(array $planes, int $tenantId, int $departamentoId, string $hoy): array
    {
        $ids = array_values(array_unique(array_map(fn (Pdi $p) => $p->colaboradorId, $planes)));
        if ($ids === []) {
            return [];
        }

        try {
            $unidades = $this->departamentoDe->actuales($tenantId, $ids, $hoy);
        } catch (Throwable) {
            return [];
        }

        return array_values(array_filter($planes, fn (Pdi $p) => ($unidades[$p->colaboradorId] ?? null) === $departamentoId));
    }
}
