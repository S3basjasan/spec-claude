<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Ports\ColaboradoresVigentesInterface;
use App\Modules\Desarrollo\Application\Ports\RelojInterface;

/** Buscador de participantes: solo colaboradores con vínculo laboral vigente. */
final class BuscarColaboradoresParaPlan
{
    public function __construct(
        private readonly ColaboradoresVigentesInterface $colaboradores,
        private readonly RelojInterface $reloj,
    ) {}

    /**
     * @return array{data: list<array{id: int, codigo: string, nombre: string}>, meta: array{page: int, per_page: int, total: int, last_page: int}}
     */
    public function ejecutar(int $tenantId, int $usuarioId, ?string $texto, int $pagina, int $porPagina): array
    {
        $resultado = $this->colaboradores->buscar($tenantId, $texto, $pagina, $porPagina, $this->reloj->hoy($usuarioId)->format('Y-m-d'));

        return ['data' => $resultado['items'], 'meta' => ['page' => $pagina, 'per_page' => $porPagina, 'total' => $resultado['total'], 'last_page' => max(1, (int) ceil($resultado['total'] / $porPagina))]];
    }
}
