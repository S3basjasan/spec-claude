<?php

namespace App\Modules\GestionPersonal\Application\DTO;

use App\Modules\GestionPersonal\Domain\Entities\CategoriaDocumental;

final class ListadoCategoriasDTO
{
    /** @param list<CategoriaDocumental> $items */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly int $pagina,
        public readonly int $porPagina,
    ) {}
}
