<?php

namespace App\Modules\Estructura\Application\DTO;

final class ListadoPosicionesDTO
{
    /**
     * @param  array<int, PosicionDTO>  $items
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

    public function desde(): ?int
    {
        return $this->items === [] ? null : ($this->pagina - 1) * $this->porPagina + 1;
    }

    public function hasta(): ?int
    {
        return $this->items === [] ? null : ($this->pagina - 1) * $this->porPagina + count($this->items);
    }
}
