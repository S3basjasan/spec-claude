<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Services\ArmadorDePlanes;
use App\Modules\Desarrollo\Domain\Exceptions\PlanNoEncontrado;
use App\Modules\Desarrollo\Domain\Repositories\PlanRepositoryInterface;

final class ObtenerPlan
{
    public function __construct(
        private readonly PlanRepositoryInterface $repositorio,
        private readonly ArmadorDePlanes $armador,
    ) {}

    /**
     * @return array<string, mixed>
     *
     * @throws PlanNoEncontrado
     */
    public function ejecutar(int $tenantId, int $usuarioId, int $planId): array
    {
        $plan = $this->repositorio->obtener($tenantId, $planId) ?? throw new PlanNoEncontrado;

        return $this->armador->plan($plan, $usuarioId);
    }
}
