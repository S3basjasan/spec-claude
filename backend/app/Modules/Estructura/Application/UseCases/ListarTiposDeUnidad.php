<?php

namespace App\Modules\Estructura\Application\UseCases;

use App\Modules\Estructura\Application\DTO\CadenaDeTiposDTO;
use App\Modules\Estructura\Domain\Repositories\TipoUnidadRepositoryInterface;
use App\Modules\Estructura\Domain\Services\CadenaDeTipos;

final class ListarTiposDeUnidad
{
    public function __construct(
        private readonly TipoUnidadRepositoryInterface $tipos,
        private readonly CadenaDeTipos $cadena,
    ) {}

    /** La cadena ordenada por nivel, con la cantidad de unidades de cada tipo. Visible para cualquier autenticado. */
    public function ejecutar(): CadenaDeTiposDTO
    {
        return CadenaDeTiposDTO::desde($this->tipos->todos(), $this->cadena);
    }
}
