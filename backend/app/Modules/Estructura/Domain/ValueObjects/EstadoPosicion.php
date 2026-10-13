<?php

namespace App\Modules\Estructura\Domain\ValueObjects;

enum EstadoPosicion: string
{
    case Ocupada = 'Ocupada';
    case Vacante = 'Vacante';
    case Suprimida = 'Suprimida';

    /** Una posición Ocupada o Vacante impide desactivar su puesto; una Suprimida no. */
    public function enUso(): bool
    {
        return $this === self::Ocupada || $this === self::Vacante;
    }
}
