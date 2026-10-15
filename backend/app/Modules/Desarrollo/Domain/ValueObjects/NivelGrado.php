<?php

namespace App\Modules\Desarrollo\Domain\ValueObjects;

/** Nivel A (más alto) a D de una competencia; propio de Desarrollo para no depender de Estructura. */
enum NivelGrado: string
{
    case A = 'A';
    case B = 'B';
    case C = 'C';
    case D = 'D';
}
