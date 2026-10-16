<?php

namespace App\Modules\Desarrollo\Domain\ValueObjects;

/** Filtros y paginación del listado de acciones de todos los planes (Lista y Calendario). */
final class FiltroAcciones
{
    /**
     * @param  EstadoPlan|null  $estadoPlan  estado del plan al que pertenece la acción
     * @param  string|null  $desde  `Y-m-d`; se devuelven las acciones que se solapan con [desde, hasta]
     */
    public function __construct(
        public readonly ?EstadoPlan $estadoPlan = null,
        public readonly ?TipoAccion $tipo = null,
        public readonly ?int $competenciaId = null,
        public readonly ?string $desde = null,
        public readonly ?string $hasta = null,
        public readonly ?int $anio = null,
        public readonly ?int $planId = null,
        public readonly int $pagina = 1,
        public readonly int $porPagina = 10,
    ) {}
}
