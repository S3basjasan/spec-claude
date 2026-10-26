<?php

namespace App\Modules\GestionPersonal\Domain\Exceptions;

use RuntimeException;

/** Falta la justificación exigida por la política conservada. */
final class JustificacionRequerida extends RuntimeException
{
    public function __construct(string $message = 'La política exige una justificación.')
    {
        parent::__construct($message);
    }
}
