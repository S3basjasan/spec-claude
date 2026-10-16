<?php

namespace App\Modules\Desarrollo\Infrastructure\Adapters\Pruebas;

use App\Modules\Desarrollo\Application\Ports\NivelesActualesInterface;
use App\Modules\Estructura\Presentation\Facades\EstructuraFacade;

/** Solo para el entorno `e2e`: niveles deterministas y variados (incluye competencias sin nivel registrado). */
final class NivelesActualesDePrueba implements NivelesActualesInterface
{
    private const POR_RESTO = [1 => 'D', 2 => 'C', 3 => 'B', 4 => 'A'];

    public function nivelesDe(int $tenantId, array $colaboradorIds): array
    {
        $competencias = EstructuraFacade::getCompetenciasActivas();
        $niveles = [];
        foreach ($colaboradorIds as $colaboradorId) {
            foreach ($competencias as $competencia) {
                $resto = ($colaboradorId + (int) $competencia['id']) % 5;
                if ($resto !== 0) {
                    $niveles[$colaboradorId][(int) $competencia['id']] = self::POR_RESTO[$resto];
                }
            }
        }

        return $niveles;
    }
}
