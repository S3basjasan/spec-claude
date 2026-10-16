<?php

namespace App\Modules\Desarrollo\Domain\ValueObjects;

/** Filtros del historial de DNF; la prioridad coincide con la prioridad más alta de la DNF. */
final class FiltroDnf
{
    public function __construct(
        public readonly ?string $fechaDesde = null,
        public readonly ?string $fechaHasta = null,
        public readonly ?int $colaboradorId = null,
        public readonly ?int $departamentoId = null,
        public readonly ?PrioridadBrecha $prioridad = null,
        public readonly int $pagina = 1,
        public readonly int $porPagina = 15,
    ) {}
}
