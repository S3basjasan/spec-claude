<?php

namespace App\Modules\Estructura\Application\UseCases;

use App\Modules\Estructura\Application\DTO\FiltroPreguntasDTO;
use App\Modules\Estructura\Application\DTO\ListadoPreguntasDTO;
use App\Modules\Estructura\Application\DTO\RegistroPreguntasDTO;
use App\Modules\Estructura\Domain\Repositories\PreguntaRepositoryInterface;
use App\Modules\Estructura\Domain\ValueObjects\EstadoCompetencia;
use App\Modules\Estructura\Domain\ValueObjects\TipoCompetencia;

final class ListarPreguntas
{
    public function __construct(private readonly PreguntaRepositoryInterface $registros) {}

    public function ejecutar(FiltroPreguntasDTO $filtro): ListadoPreguntasDTO
    {
        $q = $filtro->q === null ? null : trim($filtro->q);
        $tipo = $filtro->tipo === null ? null : TipoCompetencia::tryFrom($filtro->tipo);
        $estado = $filtro->estado === null ? null : EstadoCompetencia::tryFrom($filtro->estado);

        $pagina = $this->registros->listar($q, $tipo, $estado, $filtro->pagina, $filtro->porPagina);

        return new ListadoPreguntasDTO(
            array_map(RegistroPreguntasDTO::desde(...), $pagina->items),
            $pagina->total,
            $filtro->pagina,
            $filtro->porPagina,
        );
    }
}
