<?php

namespace App\Modules\Estructura\Domain\ValueObjects;

/**
 * Dato mínimo de una unidad para armar el árbol: identidad, código, padre y estado.
 */
final class NodoDeUnidad
{
    public function __construct(
        public readonly int $id,
        public readonly string $codigo,
        public readonly string $nombre,
        public readonly TipoDeUnidad $tipo,
        public readonly ?int $unidadPadreId,
        public readonly EstadoUnidad $estado,
        public readonly int $dependientesActivas = 0,
        public readonly int $posicionesVigentes = 0,
    ) {}
}
