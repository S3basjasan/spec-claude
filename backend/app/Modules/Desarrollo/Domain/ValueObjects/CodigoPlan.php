<?php

namespace App\Modules\Desarrollo\Domain\ValueObjects;

/** Código visible de un plan: PLAN- y el número secuencial con cuatro dígitos. */
final class CodigoPlan
{
    public static function desde(int $numero): string
    {
        return sprintf('PLAN-%04d', $numero);
    }
}
