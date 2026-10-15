<?php

namespace App\Modules\Estructura\Domain\ValueObjects;

/**
 * Estado auditable de una unidad en un momento dado: los campos que se registran en el historial.
 */
final class InstantaneaDeUnidad
{
    /**
     * @param  string|null  $fechaSupresion  `YYYY-MM-DD` o nulo.
     */
    public function __construct(
        public readonly string $nombre,
        public readonly int $tipoUnidadId,
        public readonly ?int $unidadPadreId,
        public readonly EstadoUnidad $estado,
        public readonly ?string $fechaSupresion = null,
    ) {}
}
