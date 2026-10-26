<?php

namespace App\Modules\GestionPersonal\Domain\Exceptions;

use RuntimeException;

/** Inicio de novedad o fecha efectiva anterior al día actual en la zona horaria del tenant. */
final class FechaPasada extends RuntimeException
{
    public function __construct(string $message = 'La fecha de inicio no puede ser anterior a hoy.')
    {
        parent::__construct($message);
    }
}
