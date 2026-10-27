<?php

namespace App\Modules\GestionPersonal\Domain\Exceptions;

use RuntimeException;

/** Cierre con la entrevista pendiente o en curso. */
final class EntrevistaSinResolver extends RuntimeException
{
    public function __construct(string $message = 'La tarea de entrevista de salida sigue sin resolver.')
    {
        parent::__construct($message);
    }
}
