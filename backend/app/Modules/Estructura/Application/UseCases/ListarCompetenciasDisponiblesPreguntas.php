<?php

namespace App\Modules\Estructura\Application\UseCases;

use App\Modules\Estructura\Application\DTO\CompetenciaDisponibleDTO;
use App\Modules\Estructura\Domain\Repositories\PreguntaRepositoryInterface;

final class ListarCompetenciasDisponiblesPreguntas
{
    public function __construct(private readonly PreguntaRepositoryInterface $registros) {}

    /**
     * Competencias activas que todavía no tienen registro de preguntas, ordenadas por nombre.
     *
     * @return array<int, CompetenciaDisponibleDTO>
     */
    public function ejecutar(): array
    {
        return array_map(CompetenciaDisponibleDTO::desde(...), $this->registros->disponibles());
    }
}
