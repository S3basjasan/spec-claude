<?php

namespace App\Modules\Compensaciones\Domain\ValueObjects;

enum EstadoCategoria: string
{
    case Activo = 'Activo';
    case Inactivo = 'Inactivo';
}
