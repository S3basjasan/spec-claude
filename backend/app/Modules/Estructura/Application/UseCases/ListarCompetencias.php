<?php

namespace App\Modules\Estructura\Application\UseCases;

use App\Modules\Estructura\Application\DTO\CompetenciaDTO;
use App\Modules\Estructura\Application\DTO\FiltroCompetenciasDTO;
use App\Modules\Estructura\Application\DTO\ListadoCompetenciasDTO;
use App\Modules\Estructura\Domain\Entities\Competencia;
use App\Modules\Estructura\Domain\Repositories\CompetenciaRepositoryInterface;
use App\Modules\Estructura\Domain\ValueObjects\EstadoCompetencia;
use App\Modules\Estructura\Domain\ValueObjects\TipoCompetencia;

final class ListarCompetencias
{
    public function __construct(private readonly CompetenciaRepositoryInterface $competencias) {}

    public function ejecutar(FiltroCompetenciasDTO $filtro): ListadoCompetenciasDTO
    {
        $q = $filtro->q === null ? null : trim($filtro->q);
        $tipo = $filtro->tipo === null ? null : TipoCompetencia::tryFrom($filtro->tipo);
        $estado = $filtro->estado === null ? null : EstadoCompetencia::tryFrom($filtro->estado);

        $pagina = $this->competencias->listar($q, $tipo, $estado, $filtro->pagina, $filtro->porPagina);

        return new ListadoCompetenciasDTO(
            array_map(fn (Competencia $competencia) => CompetenciaDTO::desde($competencia), $pagina->items),
            $pagina->total,
            $filtro->pagina,
            $filtro->porPagina,
        );
    }
}
