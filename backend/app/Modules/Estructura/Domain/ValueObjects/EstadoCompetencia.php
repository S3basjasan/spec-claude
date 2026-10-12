<?php

namespace App\Modules\Estructura\Domain\ValueObjects;

enum EstadoCompetencia: string
{
    case Activo = 'Activo';
    case Inactivo = 'Inactivo';

    public function opuesto(): self
    {
        return $this === self::Activo ? self::Inactivo : self::Activo;
    }
}
