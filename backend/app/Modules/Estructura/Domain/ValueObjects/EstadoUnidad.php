<?php

namespace App\Modules\Estructura\Domain\ValueObjects;

/**
 * Estado propio de una unidad organizacional (en femenino; distinto del de competencias y puestos).
 */
enum EstadoUnidad: string
{
    case Activa = 'Activa';
    case Inactiva = 'Inactiva';

    public function opuesto(): self
    {
        return $this === self::Activa ? self::Inactiva : self::Activa;
    }
}
