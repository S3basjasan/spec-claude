<?php

namespace App\Modules\Desarrollo\Domain\ValueObjects;

/** Resultado de un compromiso, que se completa al cerrar el plan. */
enum ResultadoDeCompromiso: string
{
    case Cerrada = 'Cerrada';
    case Parcial = 'Parcial';
    case NoCerrada = 'NoCerrada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Cerrada => 'Cerrada',
            self::Parcial => 'Parcial',
            self::NoCerrada => 'No cerrada',
        };
    }
}
