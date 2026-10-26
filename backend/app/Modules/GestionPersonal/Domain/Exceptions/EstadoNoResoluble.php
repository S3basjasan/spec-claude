<?php

namespace App\Modules\GestionPersonal\Domain\Exceptions;

use RuntimeException;

/** La transición no es válida desde el estado actual. */
final class EstadoNoResoluble extends RuntimeException
{
    public function __construct(string $message = 'La solicitud ya no admite esa acción en su estado actual.')
    {
        parent::__construct($message);
    }
}
