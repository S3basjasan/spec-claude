<?php

namespace App\Modules\Desarrollo\Infrastructure\Adapters;

use App\Modules\Desarrollo\Application\Ports\PlanDeFormacionInterface;
use App\Modules\Desarrollo\Domain\Exceptions\PlanNoDisponible;

/**
 * Adaptador PROVISIONAL: el módulo Plan de Formación aún no existe. Al implementarse se sustituye por uno que
 * llame a su Facade y se cambia el binding en DesarrolloServiceProvider.
 */
final class PlanDeFormacionNoDisponible implements PlanDeFormacionInterface
{
    public function crearBorrador(int $tenantId, int $actorId, int $dnfId, array $brechas): int
    {
        throw new PlanNoDisponible;
    }
}
