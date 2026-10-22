<?php

namespace App\Modules\Desarrollo\Domain\ValueObjects;

/** Búsqueda y filtros del listado de actividades. Sin fechas se aplica el año actual. */
final class FiltroActividades
{
    public function __construct(
        public readonly ?string $q = null,
        public readonly ?TipoAccion $tipo = null,
        public readonly ?EstadoAccion $estado = null,
        public readonly ?int $departamentoId = null,
        public readonly ?int $competenciaId = null,
        public readonly ?int $planId = null,
        public readonly ?string $fechaDesde = null,
        public readonly ?string $fechaHasta = null,
        public readonly int $pagina = 1,
        public readonly int $porPagina = 15,
    ) {}
}
