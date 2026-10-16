<?php

namespace App\Modules\GestionPersonal\Domain\ValueObjects;

enum EstadoDeMovilidad: string
{
    case Disponible = 'Disponible';
    case NoDisponible = 'NoDisponible';
    case NoInformada = 'NoInformada';

    /** @return list<string> */
    public static function valores(): array
    {
        return array_map(fn (self $estado) => $estado->value, self::cases());
    }
}
