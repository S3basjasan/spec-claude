<?php

namespace App\Modules\Estructura\Application\DTO;

use App\Modules\Estructura\Domain\Entities\Unidad;
use App\Modules\Estructura\Domain\ValueObjects\OpcionesDeUnidad;
use App\Modules\Estructura\Domain\ValueObjects\TipoDeUnidad;

/**
 * Datos de los selectores de los formularios de unidades.
 */
final class OpcionesDeUnidadDTO
{
    /**
     * @param  array<int, array{id: int, nombre: string, nivel: int}>  $tipos
     * @param  array<int, array<string, mixed>>  $unidades
     */
    public function __construct(
        public readonly array $tipos,
        public readonly array $unidades,
    ) {}

    public static function desde(OpcionesDeUnidad $opciones): self
    {
        return new self(
            array_map(fn (TipoDeUnidad $tipo) => ['id' => $tipo->id, 'nombre' => $tipo->nombre, 'nivel' => $tipo->nivel], $opciones->tipos),
            array_map(fn (Unidad $unidad) => [
                'id' => $unidad->id,
                'codigo' => $unidad->codigo,
                'nombre' => $unidad->nombre,
                'tipo' => ['id' => $unidad->tipo->id, 'nombre' => $unidad->tipo->nombre, 'nivel' => $unidad->tipo->nivel],
                'nivel_arbol' => $unidad->nivel,
                'estado' => $unidad->estado->value,
                'admite_hijas' => $unidad->admiteHijas,
                'habilitada' => $unidad->estaActiva() && $unidad->admiteHijas,
            ], $opciones->unidades),
        );
    }
}
