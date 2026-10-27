<?php

namespace App\Modules\GestionPersonal\Domain\Exceptions;

use RuntimeException;

/** Transición no válida desde el estado actual. */
final class EstadoNoPermitido extends RuntimeException
{
    public function __construct(string $message = 'La acción no está permitida en el estado actual del expediente.')
    {
        parent::__construct($message);
    }
}
