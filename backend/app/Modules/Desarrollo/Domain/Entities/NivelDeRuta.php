<?php

namespace App\Modules\Desarrollo\Domain\Entities;

/**
 * Nivel de una ruta: peldaño ordenado ligado a un puesto de Estructura Empresarial. Guarda solo lo propio (orden,
 * nombre y experiencia mínima); las competencias y requisitos del puesto se leen de Estructura en cada consulta.
 */
final class NivelDeRuta
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $orden,
        public readonly int $puestoId,
        public readonly string $nombre,
        public readonly ?float $experienciaMinimaAnios,
    ) {}

    public function conOrden(int $orden): self
    {
        return new self($this->id, $orden, $this->puestoId, $this->nombre, $this->experienciaMinimaAnios);
    }
}
