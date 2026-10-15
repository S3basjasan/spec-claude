<?php

namespace App\Modules\Estructura\Application\UseCases;

use App\Modules\Estructura\Application\DTO\OpcionesDeUnidadDTO;
use App\Modules\Estructura\Domain\Repositories\UnidadRepositoryInterface;

final class ListarOpcionesDeUnidad
{
    public function __construct(private readonly UnidadRepositoryInterface $unidades) {}

    /**
     * La cadena de tipos y las unidades en orden de árbol. Con `reubicar` (la unidad que se edita) solo las unidades
     * activas del tipo inmediatamente superior, sin esa unidad ni sus dependientes.
     */
    public function ejecutar(?int $reubicar = null): OpcionesDeUnidadDTO
    {
        return OpcionesDeUnidadDTO::desde($this->unidades->opciones($reubicar));
    }
}
