<?php

namespace App\Modules\Estructura\Domain\Entities;

/**
 * Tipo de unidad con la cantidad de unidades que lo usan. Los tipos nunca se eliminan.
 */
final class TipoUnidad
{
    public function __construct(
        public readonly int $id,
        public readonly string $nombre,
        public readonly int $nivel,
        public readonly int $cantidadUnidades = 0,
    ) {}

    public function esRaiz(): bool
    {
        return $this->nivel === 1;
    }
}
