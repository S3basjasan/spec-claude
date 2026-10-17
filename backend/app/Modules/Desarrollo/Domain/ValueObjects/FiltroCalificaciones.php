<?php

namespace App\Modules\Desarrollo\Domain\ValueObjects;

/** Filtros de la vista de calificaciones agregadas. `agrupar` es `actividad` o `participante`. */
final class FiltroCalificaciones
{
    public function __construct(
        public readonly string $agrupar,
        public readonly ?string $fechaDesde = null,
        public readonly ?string $fechaHasta = null,
        public readonly ?TipoAccion $tipo = null,
        public readonly ?int $departamentoId = null,
        public readonly ?int $planId = null,
        public readonly ?int $colaboradorId = null,
        public readonly int $pagina = 1,
        public readonly int $porPagina = 15,
    ) {}
}
