<?php

namespace App\Modules\GestionPersonal\Domain\Exceptions;

use RuntimeException;

/** No se pudo verificar la posición con Estructura, o no se obtuvo el bloqueo de escritura tras los reintentos. */
final class IngresoNoVerificable extends RuntimeException
{
    public function __construct(string $message = 'No se pudo verificar la posición. Inténtalo de nuevo.')
    {
        parent::__construct($message);
    }
}
