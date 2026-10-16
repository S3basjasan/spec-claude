<?php

namespace App\Modules\Desarrollo\Domain\ValueObjects;

/** Filtros y paginación del listado de planes. */
final class FiltroPlanes
{
    public function __construct(
        public readonly ?int $anio = null,
        public readonly ?EstadoPlan $estado = null,
        public readonly ?string $texto = null,
        public readonly int $pagina = 1,
        public readonly int $porPagina = 10,
    ) {}
}
