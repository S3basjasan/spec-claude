<?php

namespace App\Modules\Desarrollo\Domain\ValueObjects;

enum TipoCambioHistorial: string
{
    case AreaFormativa = 'AreaFormativa';
    case Curso = 'Curso';
    case Microcontenido = 'Microcontenido';
}
