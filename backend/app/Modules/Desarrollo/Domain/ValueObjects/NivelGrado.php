<?php

namespace App\Modules\Desarrollo\Domain\ValueObjects;

/** Nivel A (más alto) a D de una competencia; propio de Desarrollo para no depender de Estructura. */
enum NivelGrado: string
{
    case A = 'A';
    case B = 'B';
    case C = 'C';
    case D = 'D';

    /** Escala numérica del cálculo de brechas (D=1 … A=4); único lugar donde se define. */
    public function valorNumerico(): int
    {
        return match ($this) {
            self::D => 1,
            self::C => 2,
            self::B => 3,
            self::A => 4,
        };
    }
}
