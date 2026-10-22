<?php

namespace App\Modules\Desarrollo\Infrastructure\Adapters;

use App\Modules\Desarrollo\Application\Ports\ValoresDeLaPersonaInterface;
use App\Modules\Desarrollo\Domain\Repositories\DnfRepositoryInterface;

/**
 * Mientras Gestión de Personal no registre el nivel por competencia, el valor de la persona es el último nivel
 * evaluado en sus DNF. Las evaluaciones llegan de la más antigua a la más reciente; gana la última con nivel.
 */
final class ValoresDeLaPersonaDesdeDnf implements ValoresDeLaPersonaInterface
{
    public function __construct(private readonly DnfRepositoryInterface $dnf) {}

    public function nivelesDeCompetencias(int $tenantId, int $colaboradorId): array
    {
        $niveles = [];
        foreach ($this->dnf->evaluacionesDeCompetencias($tenantId, ['colaborador_id' => $colaboradorId]) as $evaluacion) {
            if ($evaluacion['nivel_actual'] !== null && in_array($evaluacion['nivel_actual'], ['A', 'B', 'C', 'D'], true)) {
                $niveles[$evaluacion['competencia_id']] = $evaluacion['nivel_actual'];
            }
        }

        return $niveles;
    }
}
