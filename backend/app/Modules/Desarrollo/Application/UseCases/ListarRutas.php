<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Ports\DepartamentosInterface;
use App\Modules\Desarrollo\Domain\Repositories\RutasRepositoryInterface;
use Throwable;

/** Lista de rutas con recuentos de niveles e inscritos. Sin permiso de gestión, solo las Vigentes. */
final class ListarRutas
{
    public function __construct(
        private readonly RutasRepositoryInterface $rutas,
        private readonly DepartamentosInterface $departamentos,
    ) {}

    /**
     * @param  array{tipo?: string|null, estado?: string|null, departamento_id?: int|null, q?: string|null}  $filtro
     * @return array{data: list<array<string, mixed>>, meta: array{page: int, per_page: int, total: int, last_page: int}}
     */
    public function ejecutar(int $tenantId, array $filtro, bool $soloVigentes, int $pagina, int $porPagina): array
    {
        $resultado = $this->rutas->listar($tenantId, $filtro + ['solo_vigentes' => $soloVigentes], $pagina, $porPagina);

        $ids = array_values(array_unique(array_filter(array_column($resultado['items'], 'departamento_id'))));
        try {
            $departamentos = $ids === [] ? [] : $this->departamentos->porIds($ids);
        } catch (Throwable) {
            $departamentos = [];
        }

        return [
            'data' => array_map(fn (array $r) => $r + ['departamento' => $r['departamento_id'] === null ? null : ($departamentos[$r['departamento_id']] ?? null)], $resultado['items']),
            'meta' => ['page' => $pagina, 'per_page' => $porPagina, 'total' => $resultado['total'], 'last_page' => max(1, (int) ceil($resultado['total'] / $porPagina))],
        ];
    }
}
