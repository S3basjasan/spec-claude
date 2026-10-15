<?php

namespace App\Modules\Desarrollo\Domain\ValueObjects;

enum TipoRecurso: string
{
    case Curso = 'curso';
    case Microcontenido = 'microcontenido';
}
