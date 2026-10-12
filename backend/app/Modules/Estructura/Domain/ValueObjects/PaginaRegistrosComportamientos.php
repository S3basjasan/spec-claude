<?php

namespace App\Modules\Estructura\Domain\ValueObjects;

use App\Modules\Estructura\Domain\Entities\RegistroComportamientos;

final class PaginaRegistrosComportamientos
{
    /**
     * @param  array<int, RegistroComportamientos>  $items
     */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
    ) {}
}
