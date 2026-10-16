<?php

namespace App\Modules\Desarrollo\Infrastructure\Adapters;

use App\Modules\Desarrollo\Application\Ports\CompetenciasInterface;
use App\Modules\Estructura\Presentation\Facades\EstructuraFacade;

/** Lee las competencias exclusivamente por la Facade publica de Estructura. */
final class CompetenciasDesdeEstructura implements CompetenciasInterface
{
    public function todas(): array
    {
        return EstructuraFacade::getTodasLasCompetencias();
    }

    public function obtener(int $id): ?array
    {
        return EstructuraFacade::getCompetencia($id);
    }

    public function porIds(array $ids): array
    {
        return EstructuraFacade::getCompetenciasPorIds($ids);
    }

    public function existe(int $id): bool
    {
        return EstructuraFacade::existeCompetencia($id);
    }

    public function estaActiva(int $id): bool
    {
        return EstructuraFacade::estaActiva($id);
    }
}
