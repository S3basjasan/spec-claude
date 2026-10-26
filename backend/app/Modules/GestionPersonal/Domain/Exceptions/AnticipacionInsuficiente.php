<?php

namespace App\Modules\GestionPersonal\Domain\Exceptions;

use RuntimeException;

/** La fecha no cumple la anticipación en días calendario de la política conservada. */
final class AnticipacionInsuficiente extends RuntimeException
{
    public function __construct(string $message = 'No se cumple la anticipación mínima exigida.')
    {
        parent::__construct($message);
    }
}
