<?php

namespace App\Modules\Desarrollo\Infrastructure\Adapters;

use App\Modules\Desarrollo\Application\Ports\NivelesActualesInterface;

/**
 * Adaptador PROVISIONAL: el nivel actual por competencia lo aportará Gestión de Personal (GP-02, aún no implementada).
 */
final class NivelesActualesSinDatos implements NivelesActualesInterface
{
    public function nivelesDe(int $tenantId, array $colaboradorIds): array
    {
        return [];
    }
}
