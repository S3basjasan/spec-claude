<?php

namespace App\Modules\Estructura\Application\UseCases;

use App\Modules\Estructura\Application\DTO\CompetenciaParaPuestoDTO;
use App\Modules\Estructura\Domain\Repositories\PuestoRepositoryInterface;

final class ListarCompetenciasParaPuestos
{
    public function __construct(private readonly PuestoRepositoryInterface $puestos) {}

    /**
     * Competencias activas con sus grados, ordenadas por nombre, para los selectores del formulario de puestos.
     *
     * @return array<int, CompetenciaParaPuestoDTO>
     */
    public function ejecutar(): array
    {
        return array_map(CompetenciaParaPuestoDTO::desde(...), $this->puestos->competenciasDisponibles());
    }
}
