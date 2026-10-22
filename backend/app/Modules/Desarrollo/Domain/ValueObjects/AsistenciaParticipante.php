<?php

namespace App\Modules\Desarrollo\Domain\ValueObjects;

/** Asistencia de un participante. Pendiente no se guarda: es la ausencia de registro. */
enum AsistenciaParticipante: string
{
    case Pendiente = 'Pendiente';
    case Presente = 'Presente';
    case Ausente = 'Ausente';
    case Justificado = 'Justificado';

    /** @return list<string> */
    public static function valores(): array
    {
        return array_map(fn (self $a) => $a->value, self::cases());
    }
}
