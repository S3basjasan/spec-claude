<?php

namespace App\Modules\Estructura\Application\DTO;

use App\Modules\Estructura\Domain\Entities\Unidad;
use App\Modules\Estructura\Domain\ValueObjects\ImpactoDeDesactivacion;

/**
 * Datos del diálogo de desactivación: dependientes activas que se desactivarían y unidades con posiciones vigentes.
 */
final class ImpactoDesactivacionDTO
{
    /**
     * @param  array<int, array{id: int, codigo: string, nombre: string, tipo: string}>  $dependientesActivas
     * @param  array<int, array{id: int, codigo: string, nombre: string, cantidad: int}>  $unidadesConPosiciones
     */
    public function __construct(
        public readonly bool $procede,
        public readonly array $dependientesActivas,
        public readonly array $unidadesConPosiciones,
    ) {}

    public static function desde(ImpactoDeDesactivacion $impacto): self
    {
        return new self(
            $impacto->procede,
            array_map(fn (Unidad $unidad) => [
                'id' => $unidad->id,
                'codigo' => $unidad->codigo,
                'nombre' => $unidad->nombre,
                'tipo' => $unidad->tipo->nombre,
            ], $impacto->dependientesActivas),
            $impacto->unidadesConPosiciones,
        );
    }
}
