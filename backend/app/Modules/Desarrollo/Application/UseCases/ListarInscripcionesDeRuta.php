<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Services\ArmadorDeInscripciones;
use App\Modules\Desarrollo\Domain\Repositories\RutasRepositoryInterface;

/** Lista de inscripciones filtrada por ruta, colaborador o estado. Concilia antes las bajas para no mostrar inscritos que ya no están. */
final class ListarInscripcionesDeRuta
{
    public function __construct(
        private readonly RutasRepositoryInterface $rutas,
        private readonly ConciliarBajasDeInscripciones $conciliar,
        private readonly ArmadorDeInscripciones $armador,
    ) {}

    /**
     * @param  array{ruta_id?: int|null, colaborador_id?: int|null, estado?: string|null}  $filtro
     * @return array{data: list<array<string, mixed>>, meta: array{page: int, per_page: int, total: int, last_page: int}}
     */
    public function ejecutar(int $tenantId, int $usuarioId, array $filtro, int $pagina, int $porPagina): array
    {
        $this->conciliar->ejecutar($tenantId, $usuarioId, $filtro['ruta_id'] ?? null);

        $resultado = $this->rutas->listarInscripciones($tenantId, $filtro, $pagina, $porPagina);

        return [
            'data' => $this->armador->lista($tenantId, $usuarioId, $resultado['items']),
            'meta' => ['page' => $pagina, 'per_page' => $porPagina, 'total' => $resultado['total'], 'last_page' => max(1, (int) ceil($resultado['total'] / $porPagina))],
        ];
    }
}
