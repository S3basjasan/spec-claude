<?php

namespace App\Modules\Estructura\Domain\ValueObjects;

use App\Modules\Estructura\Domain\Entities\Posicion;

final class PaginaDePosiciones
{
    /**
     * @param  array<int, Posicion>  $items
     */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly int $pagina,
        public readonly int $porPagina,
    ) {}
}
