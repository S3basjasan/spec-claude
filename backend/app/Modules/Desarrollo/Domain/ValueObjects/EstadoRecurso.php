<?php

namespace App\Modules\Desarrollo\Domain\ValueObjects;

enum EstadoRecurso: string
{
    case Disponible = 'Disponible';
    case Archivado = 'Archivado';

    public function estaDisponible(): bool
    {
        return $this === self::Disponible;
    }
}
