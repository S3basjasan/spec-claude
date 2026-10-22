<?php

namespace App\Modules\Desarrollo\Infrastructure\Adapters;

use App\Modules\Desarrollo\Application\Ports\DepartamentoDeColaboradorInterface;
use App\Modules\GestionPersonal\Presentation\Facades\PersonalFacade;

/** Departamento actual de cada colaborador por la Facade pública de Gestión de Personal. */
final class DepartamentoDeColaboradorDesdePersonal implements DepartamentoDeColaboradorInterface
{
    public function actuales(int $tenantId, array $colaboradorIds, string $fecha): array
    {
        if ($colaboradorIds === []) {
            return [];
        }

        return PersonalFacade::getDepartamentoActual($tenantId, array_values(array_unique($colaboradorIds)), $fecha);
    }
}
