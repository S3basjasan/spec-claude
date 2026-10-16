<?php

namespace App\Modules\Desarrollo\Domain\ValueObjects;

final class PaginaDeResumenes
{
    /**
     * @param  list<ResumenDnf>  $items
     */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly int $pagina,
        public readonly int $porPagina,
    ) {}

    public function ultimaPagina(): int
    {
        return max(1, (int) ceil($this->total / max(1, $this->porPagina)));
    }
}
