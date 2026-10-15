<?php

namespace App\Modules\Estructura\Domain\ValueObjects;

use App\Modules\Estructura\Domain\Entities\Unidad;

/**
 * Qué pasaría al desactivar una unidad: las dependientes activas que también se desactivarían y las unidades de la
 * rama con posiciones Ocupadas o Vacantes (que impiden la desactivación).
 */
final class ImpactoDeDesactivacion
{
    /**
     * @param  array<int, Unidad>  $dependientesActivas  Directas e indirectas, en orden jerárquico.
     * @param  array<int, array{id: int, codigo: string, nombre: string, cantidad: int}>  $unidadesConPosiciones
     */
    public function __construct(
        public readonly bool $procede,
        public readonly array $dependientesActivas,
        public readonly array $unidadesConPosiciones,
    ) {}
}
