<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Services\ArmadorDePlanes;
use App\Modules\Desarrollo\Application\Services\ConstructorDePlan;
use App\Modules\Desarrollo\Domain\Exceptions\NombreDePlanDuplicado;
use App\Modules\Desarrollo\Domain\Repositories\PlanRepositoryInterface;

/** Crea un plan en Borrador; solo se exige lo que un Borrador necesita. */
final class CrearPlan
{
    public function __construct(
        private readonly PlanRepositoryInterface $repositorio,
        private readonly ConstructorDePlan $constructor,
        private readonly ArmadorDePlanes $armador,
    ) {}

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     *
     * @throws NombreDePlanDuplicado
     */
    public function ejecutar(int $tenantId, int $actorId, array $datos): array
    {
        $plan = $this->constructor->construir($tenantId, $actorId, $datos);
        if ($this->repositorio->existeNombre($tenantId, $plan->anio, $plan->nombreNormalizado)) {
            throw new NombreDePlanDuplicado;
        }

        return $this->armador->plan($this->repositorio->crear($plan), $actorId);
    }
}
