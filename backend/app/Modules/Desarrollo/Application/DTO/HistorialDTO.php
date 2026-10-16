<?php

namespace App\Modules\Desarrollo\Application\DTO;

final class HistorialDTO
{
    /**
     * @param  array<int, array{fecha: string, usuario: string, tipo_cambio: string, accion: string, recurso: ?string, valor_anterior: ?string, valor_nuevo: ?string}>  $items
     */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly int $pagina,
        public readonly int $porPagina,
    ) {}
}
