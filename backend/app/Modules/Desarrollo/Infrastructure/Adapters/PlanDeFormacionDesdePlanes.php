<?php

namespace App\Modules\Desarrollo\Infrastructure\Adapters;

use App\Modules\Desarrollo\Application\Ports\PlanDeFormacionInterface;
use App\Modules\Desarrollo\Application\UseCases\CrearBorradorDesdeDnf;

/** Crea el borrador pedido por la DNF con el caso de uso del propio módulo (reemplaza al adaptador provisional de 006). */
final class PlanDeFormacionDesdePlanes implements PlanDeFormacionInterface
{
    public function __construct(private readonly CrearBorradorDesdeDnf $crear) {}

    public function crearBorrador(int $tenantId, int $actorId, int $dnfId, array $brechas): int
    {
        return $this->crear->ejecutar($tenantId, $actorId, $dnfId, $brechas);
    }
}
