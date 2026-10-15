<?php

namespace App\Modules\Estructura\Domain\ValueObjects;

use App\Modules\Estructura\Domain\Entities\Unidad;

/**
 * Datos de los selectores de los formularios: la cadena de tipos y las unidades que pueden ser unidad padre.
 */
final class OpcionesDeUnidad
{
    /**
     * @param  array<int, TipoDeUnidad>  $tipos  Por nivel.
     * @param  array<int, Unidad>  $unidades  En orden jerárquico. Al crear, todas (las inactivas y las del último nivel
     *                                        se muestran deshabilitadas); al reubicar, solo las candidatas válidas.
     */
    public function __construct(
        public readonly array $tipos,
        public readonly array $unidades,
    ) {}
}
