<?php

namespace App\Modules\GestionPersonal\Domain\Entities;

use App\Modules\GestionPersonal\Domain\Services\PeriodoLaboral;

final class AsignacionLaboral
{
    public function __construct(
        public readonly int $id,
        public readonly int $vinculoId,
        public readonly int $posicionId,
        public readonly string $fechaInicio,
        public readonly ?string $fechaFin,
    ) {}

    public function periodo(): PeriodoLaboral
    {
        return new PeriodoLaboral($this->fechaInicio, $this->fechaFin);
    }
}
