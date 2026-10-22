<?php

namespace App\Modules\Desarrollo\Application\DTO;

final class ListadoMicroDTO
{
    /**
     * @param  array<int, MicroDTO>  $items
     */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly int $pagina,
        public readonly int $porPagina,
    ) {}
}
