<?php

namespace App\Modules\Estructura\Domain\ValueObjects;

use App\Modules\Estructura\Domain\Entities\Competencia;

final class PaginaCompetencias
{
    /**
     * @param  array<int, Competencia>  $items
     */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
    ) {}
}
