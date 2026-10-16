<?php

namespace App\Modules\GestionPersonal\Domain\ValueObjects;

enum EstadoDeCategoria: string
{
    case Activa = 'Activa';
    case Inactiva = 'Inactiva';

    /** @return list<string> */
    public static function valores(): array
    {
        return array_map(fn (self $estado) => $estado->value, self::cases());
    }
}
