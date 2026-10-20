<?php

namespace App\Modules\Compensaciones\Domain\Exceptions;

use InvalidArgumentException;

/**
 * Invariante de dominio incumplida. La validación de entrada ya la cubre el Request; esta es la red de seguridad.
 */
final class DatosDeCategoriaInvalidos extends InvalidArgumentException
{
    public function __construct(public readonly string $campo, string $message)
    {
        parent::__construct($message);
    }
}
