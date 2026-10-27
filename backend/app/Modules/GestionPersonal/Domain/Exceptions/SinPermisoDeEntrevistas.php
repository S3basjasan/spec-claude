<?php

namespace App\Modules\GestionPersonal\Domain\Exceptions;

use RuntimeException;

/** Falta el permiso específico para consultar respuestas. */
final class SinPermisoDeEntrevistas extends RuntimeException
{
    public function __construct(string $message = 'No tienes permiso para consultar las respuestas de las entrevistas de salida.')
    {
        parent::__construct($message);
    }
}
