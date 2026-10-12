<?php

namespace App\Modules\Estructura\Application\DTO;

final class ListadoComportamientosDTO
{
    /**
     * @param  array<int, RegistroComportamientosDTO>  $items
     */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly int $pagina,
        public readonly int $porPagina,
    ) {}
}
