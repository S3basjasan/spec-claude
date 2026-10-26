<?php

namespace App\Modules\GestionPersonal\Domain\Exceptions;

use RuntimeException;

/** Otro movimiento aprobado del mismo colaborador sigue pendiente. */
final class MovimientoIncompatible extends RuntimeException
{
    public function __construct(string $message = 'La persona ya tiene otro movimiento aprobado pendiente de aplicar.')
    {
        parent::__construct($message);
    }
}
