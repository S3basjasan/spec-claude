<?php

namespace App\Modules\Estructura\Domain\ValueObjects;

enum TipoCompetencia: string
{
    case Cardinal = 'Cardinal';
    case Especifica = 'Especifica';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Cardinal => 'Cardinal',
            self::Especifica => 'Específica',
        };
    }
}
