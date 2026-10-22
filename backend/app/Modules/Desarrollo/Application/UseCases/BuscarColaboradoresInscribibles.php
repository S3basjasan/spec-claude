<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Ports\ColaboradoresVigentesInterface;
use App\Modules\Desarrollo\Application\Ports\RelojInterface;
use App\Modules\Desarrollo\Domain\Exceptions\FuenteNoDisponibleParaRuta;
use Throwable;

/** Colaboradores que se pueden inscribir: solo los que tienen vínculo laboral vigente, de cualquier departamento. */
final class BuscarColaboradoresInscribibles
{
    public function __construct(
        private readonly ColaboradoresVigentesInterface $colaboradores,
        private readonly RelojInterface $reloj,
    ) {}

    /**
     * @return array{data: list<array{id: int, codigo: string, nombre: string}>, meta: array{page: int, per_page: int, total: int, last_page: int}}
     *
     * @throws FuenteNoDisponibleParaRuta
     */
    public function ejecutar(int $tenantId, int $usuarioId, ?string $texto, int $pagina, int $porPagina): array
    {
        try {
            $resultado = $this->colaboradores->buscar($tenantId, $texto, $pagina, $porPagina, $this->reloj->hoy($usuarioId)->format('Y-m-d'));
        } catch (Throwable) {
            throw new FuenteNoDisponibleParaRuta('Gestión de Personal');
        }

        return [
            'data' => $resultado['items'],
            'meta' => ['page' => $pagina, 'per_page' => $porPagina, 'total' => $resultado['total'], 'last_page' => max(1, (int) ceil($resultado['total'] / $porPagina))],
        ];
    }
}
