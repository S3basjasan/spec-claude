<?php

namespace App\Modules\Estructura\Application\DTO;

use App\Modules\Estructura\Domain\ValueObjects\OpcionesDePosicion;

/**
 * Datos de los selectores del formulario de posiciones; las posiciones candidatas llevan el nombre de su ocupante.
 */
final class OpcionesDePosicionDTO
{
    /**
     * @param  array<int, array<string, mixed>>  $puestos
     * @param  array<int, array<string, mixed>>  $unidades
     * @param  array<int, array<string, mixed>>  $posiciones
     */
    public function __construct(
        public readonly array $puestos,
        public readonly array $unidades,
        public readonly array $posiciones,
        public readonly bool $hayMaximaAutoridad,
        /** @var array<int, int> */
        public readonly array $unidadesMinimas = [],
    ) {}

    /**
     * @param  array<int, string>  $nombres  id de usuario → nombre
     */
    public static function desde(OpcionesDePosicion $opciones, array $nombres): self
    {
        return new self(
            $opciones->puestos,
            $opciones->unidades,
            array_map(fn (array $p) => [
                'id' => $p['id'],
                'codigo' => $p['codigo'],
                'puesto' => $p['puesto'],
                'unidad_organizacional_id' => $p['unidad_organizacional_id'],
                'unidad' => $p['unidad'],
                'ocupante' => $p['ocupante_id'] === null ? null : ($nombres[$p['ocupante_id']] ?? null),
            ], $opciones->posiciones),
            $opciones->hayMaximaAutoridad,
            $opciones->unidadesMinimas,
        );
    }
}
