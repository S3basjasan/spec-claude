<?php

namespace App\Modules\Estructura\Domain\ValueObjects;

/**
 * Clave posicional estable de un grado (A = orden 1 … D = orden 4). No depende de la escala con que se nombre.
 */
enum GradoLetra: string
{
    case A = 'A';
    case B = 'B';
    case C = 'C';
    case D = 'D';

    /**
     * @return array<int, string>
     */
    public static function letras(): array
    {
        return array_map(fn (self $grado) => $grado->value, self::cases());
    }

    public static function desdeOrden(int $orden): string
    {
        return self::letras()[$orden - 1] ?? self::A->value;
    }

    public static function aOrden(string $letra): int
    {
        return (int) array_search($letra, self::letras(), true) + 1;
    }
}
