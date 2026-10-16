<?php

namespace App\Modules\GestionPersonal\Domain\ValueObjects;

enum TipoIdentificacion: string
{
    case Cedula = 'Cedula';
    case Pasaporte = 'Pasaporte';
    case Otro = 'Otro';

    /** @return list<string> */
    public static function valores(): array
    {
        return array_map(fn (self $tipo) => $tipo->value, self::cases());
    }
}
