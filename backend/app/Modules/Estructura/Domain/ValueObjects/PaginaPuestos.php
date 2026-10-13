<?php

namespace App\Modules\Estructura\Domain\ValueObjects;

use App\Modules\Estructura\Domain\Entities\Puesto;

final class PaginaPuestos
{
    /**
     * @param  array<int, Puesto>  $items
     */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
    ) {}
}
