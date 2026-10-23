<?php

namespace App\Modules\Desarrollo\Domain\ValueObjects;

/** Resultado del plan, calculado al cerrar a partir de los resultados de los compromisos (FR-033). */
enum ResultadoDePdi: string
{
    case BrechaCerrada = 'BrechaCerrada';
    case Parcial = 'Parcial';
    case NoCerrada = 'NoCerrada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::BrechaCerrada => 'Brecha cerrada',
            self::Parcial => 'Parcial',
            self::NoCerrada => 'No cerrada',
        };
    }
}
