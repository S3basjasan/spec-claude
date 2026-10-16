<?php

namespace App\Modules\Desarrollo\Infrastructure\Adapters\Pruebas;

use App\Modules\Desarrollo\Application\Ports\PlanDeFormacionInterface;

/** Solo para el entorno `e2e`: simula la creación del borrador y devuelve un identificador. */
final class PlanDeFormacionDePrueba implements PlanDeFormacionInterface
{
    public function crearBorrador(int $tenantId, int $actorId, int $dnfId, array $brechas): int
    {
        return 100000 + $dnfId;
    }
}
