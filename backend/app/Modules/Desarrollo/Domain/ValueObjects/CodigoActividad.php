<?php

namespace App\Modules\Desarrollo\Domain\ValueObjects;

/** Código visible de una actividad: ACT- y el número secuencial con cuatro dígitos. */
final class CodigoActividad
{
    public static function desde(int $numero): string
    {
        return sprintf('ACT-%04d', $numero);
    }
}
