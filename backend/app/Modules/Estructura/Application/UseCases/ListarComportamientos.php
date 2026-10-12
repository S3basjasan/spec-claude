<?php

namespace App\Modules\Estructura\Application\UseCases;

use App\Modules\Estructura\Application\DTO\FiltroComportamientosDTO;
use App\Modules\Estructura\Application\DTO\ListadoComportamientosDTO;
use App\Modules\Estructura\Application\DTO\RegistroComportamientosDTO;
use App\Modules\Estructura\Domain\Repositories\ComportamientoRepositoryInterface;
use App\Modules\Estructura\Domain\ValueObjects\EstadoCompetencia;
use App\Modules\Estructura\Domain\ValueObjects\TipoCompetencia;

final class ListarComportamientos
{
    public function __construct(private readonly ComportamientoRepositoryInterface $registros) {}

    public function ejecutar(FiltroComportamientosDTO $filtro): ListadoComportamientosDTO
    {
        $q = $filtro->q === null ? null : trim($filtro->q);
        $tipo = $filtro->tipo === null ? null : TipoCompetencia::tryFrom($filtro->tipo);
        $estado = $filtro->estado === null ? null : EstadoCompetencia::tryFrom($filtro->estado);

        $pagina = $this->registros->listar($q, $tipo, $estado, $filtro->pagina, $filtro->porPagina);

        return new ListadoComportamientosDTO(
            array_map(RegistroComportamientosDTO::desde(...), $pagina->items),
            $pagina->total,
            $filtro->pagina,
            $filtro->porPagina,
        );
    }
}
