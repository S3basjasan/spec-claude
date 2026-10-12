<?php

namespace App\Modules\Estructura\Application\UseCases;

use App\Modules\Estructura\Application\DTO\CompetenciaDisponibleDTO;
use App\Modules\Estructura\Domain\Repositories\ComportamientoRepositoryInterface;

final class ListarCompetenciasDisponibles
{
    public function __construct(private readonly ComportamientoRepositoryInterface $registros) {}

    /**
     * Competencias activas que todavía no tienen registro, ordenadas por nombre.
     *
     * @return array<int, CompetenciaDisponibleDTO>
     */
    public function ejecutar(): array
    {
        return array_map(CompetenciaDisponibleDTO::desde(...), $this->registros->disponibles());
    }
}
