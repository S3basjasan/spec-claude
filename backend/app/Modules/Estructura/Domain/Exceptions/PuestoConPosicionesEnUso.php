<?php

namespace App\Modules\Estructura\Domain\Exceptions;

use RuntimeException;

/**
 * Un puesto con posiciones Ocupadas o Vacantes no puede desactivarse; `cantidad` es la suma de ambas. Con
 * `conAsignaciones` también cuentan las posiciones con una asignación laboral vigente o futura.
 */
final class PuestoConPosicionesEnUso extends RuntimeException
{
    public function __construct(public readonly int $cantidad, bool $conAsignaciones = false)
    {
        if ($cantidad === 1) {
            $mensaje = $conAsignaciones
                ? 'No se puede desactivar el puesto: tiene 1 posición ocupada, vacante o con asignación vigente.'
                : 'No se puede desactivar el puesto: tiene 1 posición ocupada o vacante.';
        } else {
            $mensaje = $conAsignaciones
                ? "No se puede desactivar el puesto: tiene $cantidad posiciones ocupadas, vacantes o con asignaciones vigentes."
                : "No se puede desactivar el puesto: tiene $cantidad posiciones ocupadas o vacantes.";
        }

        parent::__construct($mensaje);
    }
}
