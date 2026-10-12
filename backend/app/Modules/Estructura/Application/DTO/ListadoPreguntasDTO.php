<?php

namespace App\Modules\Estructura\Application\DTO;

final class ListadoPreguntasDTO
{
    /**
     * @param  array<int, RegistroPreguntasDTO>  $items
     */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly int $pagina,
        public readonly int $porPagina,
    ) {}
}
