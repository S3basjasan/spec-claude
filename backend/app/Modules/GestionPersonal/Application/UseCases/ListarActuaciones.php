<?php

namespace App\Modules\GestionPersonal\Application\UseCases;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Domain\Repositories\ActuacionRepositoryInterface;

/** Listado paginado de movimientos y novedades del tenant del Administrador. */
final class ListarActuaciones
{
    public function __construct(private readonly ActuacionRepositoryInterface $actuaciones) {}

    /**
     * @param  array{tipo?: string|null, estado?: string|null, grupo?: string|null, colaborador_id?: int|null, desde?: string|null, hasta?: string|null}  $filtros
     * @return array{items: list<array<string, mixed>>, total: int, pagina: int, por_pagina: int}
     */
    public function ejecutar(ContextoDeTenant $contexto, array $filtros, int $pagina, int $porPagina): array
    {
        $resultado = $this->actuaciones->listar($contexto->tenantId, $filtros, $pagina, $porPagina);

        return ['items' => $resultado['items'], 'total' => $resultado['total'], 'pagina' => $pagina, 'por_pagina' => $porPagina];
    }
}
