<?php

namespace App\Modules\Estructura\Domain\ValueObjects;

/**
 * Dato mínimo de una posición para armar el árbol de supervisión: identidad, código, supervisor y estado.
 */
final class NodoDePosicion
{
    public function __construct(
        public readonly int $id,
        public readonly string $codigo,
        public readonly ?int $supervisorId,
        public readonly EstadoPosicion $estado,
    ) {}
}
