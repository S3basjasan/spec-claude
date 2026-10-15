<?php

namespace App\Modules\Estructura\Application\UseCases;

use App\Modules\Estructura\Application\DTO\ImpactoDesactivacionDTO;
use App\Modules\Estructura\Domain\Exceptions\UnidadNoEncontrada;
use App\Modules\Estructura\Domain\Repositories\UnidadRepositoryInterface;

final class ConsultarImpactoDeDesactivacion
{
    public function __construct(private readonly UnidadRepositoryInterface $unidades) {}

    /**
     * Datos del diálogo de confirmación. La decisión final la toma siempre el cambio de estado.
     *
     * @throws UnidadNoEncontrada
     */
    public function ejecutar(int $id): ImpactoDesactivacionDTO
    {
        return ImpactoDesactivacionDTO::desde($this->unidades->impactoDeDesactivacion($id));
    }
}
