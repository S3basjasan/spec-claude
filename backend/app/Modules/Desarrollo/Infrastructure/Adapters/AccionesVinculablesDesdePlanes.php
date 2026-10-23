<?php

namespace App\Modules\Desarrollo\Infrastructure\Adapters;

use App\Modules\Desarrollo\Application\Ports\AccionesVinculablesInterface;
use App\Modules\Desarrollo\Domain\Repositories\PlanRepositoryInterface;

/** Lee del Plan de Formación vigente las acciones vinculables (sin copiarlas). */
final class AccionesVinculablesDesdePlanes implements AccionesVinculablesInterface
{
    public function __construct(private readonly PlanRepositoryInterface $planes) {}

    public function deColaborador(int $tenantId, int $colaboradorId): array
    {
        $ids = $this->planes->idsDeAccionesVinculablesDeColaborador($tenantId, $colaboradorId);

        return array_values($this->planes->accionesPorIds($tenantId, $ids));
    }

    public function porIds(int $tenantId, array $accionIds): array
    {
        return $this->planes->accionesPorIds($tenantId, $accionIds);
    }
}
