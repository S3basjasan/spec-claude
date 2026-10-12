<?php

namespace App\Modules\Estructura\Domain\ValueObjects;

use App\Modules\Estructura\Domain\Entities\RegistroPreguntas;

final class PaginaRegistrosPreguntas
{
    /**
     * @param  array<int, RegistroPreguntas>  $items
     */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
    ) {}
}
