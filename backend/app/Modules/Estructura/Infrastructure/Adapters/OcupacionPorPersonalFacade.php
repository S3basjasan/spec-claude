<?php

namespace App\Modules\Estructura\Infrastructure\Adapters;

use App\Modules\Estructura\Application\Ports\OcupacionDePosicionesPort;
use App\Modules\GestionPersonal\Presentation\Facades\PersonalFacade;

/** Consulta la ocupación por el contrato público de Gestión de Personal. */
final class OcupacionPorPersonalFacade implements OcupacionDePosicionesPort
{
    public function conAsignacionVigenteOFutura(array $posicionIds, string $fecha): array
    {
        if ($posicionIds === []) {
            return [];
        }

        return array_map('intval', PersonalFacade::posicionesConAsignacionVigenteOFutura($posicionIds, $fecha));
    }
}
