<?php

namespace App\Modules\Desarrollo\Domain\ValueObjects;

enum EstadoBrecha: string
{
    case ConBrecha = 'ConBrecha';
    case Cumple = 'Cumple';
    case Supera = 'Supera';
    case SinEvaluacion = 'SinEvaluacion';
}
