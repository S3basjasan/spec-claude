<?php

namespace App\Modules\Estructura\Domain\ValueObjects;

/**
 * Datos editables de una unidad: nombre y unidad padre (nula solo en la raíz). El tipo lo calcula el sistema; el estado y
 * las fechas no se editan.
 */
final class DatosUnidad
{
    public function __construct(
        public readonly string $nombre,
        public readonly ?int $unidadPadreId,
    ) {}
}
