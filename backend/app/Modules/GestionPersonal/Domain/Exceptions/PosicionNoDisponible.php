<?php

namespace App\Modules\GestionPersonal\Domain\Exceptions;

use RuntimeException;

/** La posición no admite el ingreso: ocupada en el periodo, suprimida, puesto o unidad inactivos, o ajena al tenant. */
final class PosicionNoDisponible extends RuntimeException
{
    public function __construct(string $message = 'La posición no está disponible para esa fecha.')
    {
        parent::__construct($message);
    }
}
