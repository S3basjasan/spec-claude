<?php

namespace App\Modules\Desarrollo\Application\DTO;

final class PaginaHistorialDnfDTO
{
    /**
     * @param  list<FilaHistorialDnfDTO>  $filas
     */
    public function __construct(
        public readonly array $filas,
        public readonly int $total,
        public readonly int $pagina,
        public readonly int $porPagina,
        public readonly int $ultimaPagina,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'data' => array_map(fn (FilaHistorialDnfDTO $f) => $f->toArray(), $this->filas),
            'meta' => ['page' => $this->pagina, 'per_page' => $this->porPagina, 'total' => $this->total, 'last_page' => $this->ultimaPagina],
        ];
    }
}
