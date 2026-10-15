<?php

namespace App\Modules\Estructura\Domain\ValueObjects;

/**
 * Tipo de la cadena de tipos de unidad: `nivel` 1 es el tipo raíz y los niveles son continuos 1..N.
 */
final class TipoDeUnidad
{
    public function __construct(
        public readonly int $id,
        public readonly string $nombre,
        public readonly int $nivel = 1,
    ) {}
}
