<?php

namespace App\Modules\Desarrollo\Domain\ValueObjects;

enum EstadoIntento: string
{
    case EnCurso = 'EnCurso';
    case Finalizado = 'Finalizado';
}
