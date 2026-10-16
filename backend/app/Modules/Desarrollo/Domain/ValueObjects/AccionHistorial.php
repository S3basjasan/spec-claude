<?php

namespace App\Modules\Desarrollo\Domain\ValueObjects;

enum AccionHistorial: string
{
    case Asociado = 'Asociado';
    case Quitado = 'Quitado';
    case Cambiado = 'Cambiado';
    case Renombrado = 'Renombrado';
}
