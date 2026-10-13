<?php

namespace App\Modules\Estructura\Application\UseCases;

use App\Modules\Estructura\Application\DTO\FiltroPuestosDTO;
use App\Modules\Estructura\Application\DTO\ListadoPuestosDTO;
use App\Modules\Estructura\Application\DTO\PuestoDTO;
use App\Modules\Estructura\Domain\Repositories\PuestoRepositoryInterface;
use App\Modules\Estructura\Domain\ValueObjects\EstadoPuesto;

final class ListarPuestos
{
    public function __construct(private readonly PuestoRepositoryInterface $puestos) {}

    public function ejecutar(FiltroPuestosDTO $filtro): ListadoPuestosDTO
    {
        $q = $filtro->q === null ? null : trim($filtro->q);
        $estado = $filtro->estado === null ? null : EstadoPuesto::tryFrom($filtro->estado);

        $pagina = $this->puestos->listar($q, $estado, $filtro->pagina, $filtro->porPagina);

        return new ListadoPuestosDTO(
            array_map(PuestoDTO::desde(...), $pagina->items),
            $pagina->total,
            $filtro->pagina,
            $filtro->porPagina,
        );
    }
}
