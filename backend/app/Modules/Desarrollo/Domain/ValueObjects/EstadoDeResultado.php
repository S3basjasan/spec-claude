<?php

namespace App\Modules\Desarrollo\Domain\ValueObjects;

/** Estado de un requisito evaluado: diferencia negativa, cero, positiva o sin valor de la persona. */
enum EstadoDeResultado: string
{
    case ConBrecha = 'ConBrecha';
    case Cumple = 'Cumple';
    case Supera = 'Supera';
    case SinEvaluacion = 'SinEvaluacion';

    public function etiqueta(): string
    {
        return match ($this) {
            self::ConBrecha => 'Con brecha',
            self::Cumple => 'Cumple',
            self::Supera => 'Supera',
            self::SinEvaluacion => 'Sin evaluación',
        };
    }
}
