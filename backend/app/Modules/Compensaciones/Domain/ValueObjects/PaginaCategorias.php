<?php

namespace App\Modules\Compensaciones\Domain\ValueObjects;

use App\Modules\Compensaciones\Domain\Entities\Categoria;

final class PaginaCategorias
{
    /**
     * @param  array<int, Categoria>  $items
     */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly int $pagina,
        public readonly int $porPagina,
        public readonly int $ultimaPagina,
    ) {}
}
