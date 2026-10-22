<?php

namespace App\Modules\Desarrollo\Domain\ValueObjects;

enum EstadoProgresoMicro: string
{
    case EnProgreso = 'EnProgreso';
    case Completado = 'Completado';
}
