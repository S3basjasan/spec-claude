<?php

namespace App\Modules\Estructura\Application\DTO;

use App\Modules\Estructura\Domain\Entities\TipoUnidad;
use App\Modules\Estructura\Domain\Services\CadenaDeTipos;

/**
 * La cadena de tipos por nivel y su token de edición simultánea (`version`).
 */
final class CadenaDeTiposDTO
{
    /**
     * @param  array<int, array{id: int, nombre: string, nivel: int, es_raiz: bool, cantidad_unidades: int}>  $tipos
     */
    public function __construct(
        public readonly array $tipos,
        public readonly string $version,
    ) {}

    /**
     * @param  array<int, TipoUnidad>  $tipos
     */
    public static function desde(array $tipos, CadenaDeTipos $cadena): self
    {
        return new self(
            array_map(fn (TipoUnidad $tipo) => self::tipo($tipo), $tipos),
            $cadena->token($tipos),
        );
    }

    /**
     * @return array{id: int, nombre: string, nivel: int, es_raiz: bool, cantidad_unidades: int}
     */
    public static function tipo(TipoUnidad $tipo): array
    {
        return [
            'id' => $tipo->id,
            'nombre' => $tipo->nombre,
            'nivel' => $tipo->nivel,
            'es_raiz' => $tipo->esRaiz(),
            'cantidad_unidades' => $tipo->cantidadUnidades,
        ];
    }
}
