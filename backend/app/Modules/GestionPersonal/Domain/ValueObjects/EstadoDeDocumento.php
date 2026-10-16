<?php

namespace App\Modules\GestionPersonal\Domain\ValueObjects;

enum EstadoDeDocumento: string
{
    case Disponible = 'Disponible';
    case Archivado = 'Archivado';

    /** @return list<string> */
    public static function valores(): array
    {
        return array_map(fn (self $estado) => $estado->value, self::cases());
    }
}
