<?php

namespace App\Modules\Estructura\Domain\ValueObjects;

/**
 * Un tipo en el borrador de la cadena: su id y el nombre propuesto. El nivel es su posición en la lista (1..N).
 */
final class BorradorDeTipo
{
    public function __construct(
        public readonly int $id,
        public readonly string $nombre,
    ) {}
}
