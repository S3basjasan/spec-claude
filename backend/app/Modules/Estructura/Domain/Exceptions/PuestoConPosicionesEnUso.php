<?php

namespace App\Modules\Estructura\Domain\Exceptions;

use RuntimeException;

/**
 * Un puesto con posiciones Ocupadas o Vacantes no puede desactivarse; `cantidad` es la suma de ambas.
 */
final class PuestoConPosicionesEnUso extends RuntimeException
{
    public function __construct(public readonly int $cantidad)
    {
        parent::__construct(
            $cantidad === 1
                ? 'No se puede desactivar el puesto: tiene 1 posición ocupada o vacante.'
                : "No se puede desactivar el puesto: tiene $cantidad posiciones ocupadas o vacantes."
        );
    }
}
