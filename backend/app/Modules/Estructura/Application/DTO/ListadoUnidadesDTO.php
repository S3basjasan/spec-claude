<?php

namespace App\Modules\Estructura\Application\DTO;

final class ListadoUnidadesDTO
{
    /**
     * @param  array<int, UnidadDTO>  $items  En orden jerárquico, con su nivel real; incluye las unidades superiores de
     *                                        las coincidencias para poder armar el árbol.
     * @param  int  $coincidencias  Unidades que cumplen la búsqueda y el filtro (sin contar las superiores añadidas).
     */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly int $coincidencias,
    ) {}
}
