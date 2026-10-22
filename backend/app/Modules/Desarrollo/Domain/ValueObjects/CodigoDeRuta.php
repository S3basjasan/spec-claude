<?php

namespace App\Modules\Desarrollo\Domain\ValueObjects;

/** Código visible de una ruta: CAR- y el número secuencial con cuatro dígitos. */
final class CodigoDeRuta
{
    public static function desde(int $numero): string
    {
        return sprintf('CAR-%04d', $numero);
    }

    public static function esValido(string $codigo): bool
    {
        return preg_match('/^CAR-\d{4,}$/', $codigo) === 1;
    }
}
