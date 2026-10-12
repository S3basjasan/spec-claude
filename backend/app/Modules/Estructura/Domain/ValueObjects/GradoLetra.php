<?php

namespace App\Modules\Estructura\Domain\ValueObjects;

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
}
