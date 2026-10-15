<?php

namespace App\Modules\Estructura\Domain\ValueObjects;

/**
 * Referencia breve a una unidad (la unidad padre, o una unidad dependiente con su estado).
 */
final class UnidadResumida
{
    public function __construct(
        public readonly int $id,
        public readonly string $codigo,
        public readonly string $nombre,
        public readonly ?EstadoUnidad $estado = null,
    ) {}
}
