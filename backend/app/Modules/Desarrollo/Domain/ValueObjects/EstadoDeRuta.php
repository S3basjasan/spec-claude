<?php

namespace App\Modules\Desarrollo\Domain\ValueObjects;

/** Estado de una ruta de carrera. */
enum EstadoDeRuta: string
{
    case Borrador = 'Borrador';
    case Vigente = 'Vigente';
    case Archivada = 'Archivada';

    /** Transiciones válidas: Borrador→Vigente, Vigente→Archivada y Archivada→Vigente. */
    public function puedePasarA(self $destino): bool
    {
        return match ($this) {
            self::Borrador => $destino === self::Vigente,
            self::Vigente => $destino === self::Archivada,
            self::Archivada => $destino === self::Vigente,
        };
    }
}
