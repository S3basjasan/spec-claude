<?php

namespace App\Modules\GestionPersonal\Domain\ValueObjects;

enum EstadoDeFormacion: string
{
    case EnCurso = 'EnCurso';
    case Completada = 'Completada';
    case Incompleta = 'Incompleta';

    /** @return list<string> */
    public static function valores(): array
    {
        return array_map(fn (self $estado) => $estado->value, self::cases());
    }
}
