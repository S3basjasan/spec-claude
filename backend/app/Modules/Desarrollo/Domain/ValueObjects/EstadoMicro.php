<?php

namespace App\Modules\Desarrollo\Domain\ValueObjects;

enum EstadoMicro: string
{
    case Borrador = 'Borrador';
    case Publicado = 'Publicado';
    case Archivado = 'Archivado';

    /** Transiciones permitidas: Borrador→Publicado, Publicado→Archivado y Archivado→Publicado. */
    public function puedeIrA(self $nuevo): bool
    {
        return match ($this) {
            self::Borrador => $nuevo === self::Publicado,
            self::Publicado => $nuevo === self::Archivado,
            self::Archivado => $nuevo === self::Publicado,
        };
    }
}
