<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Services\ArmadorDePlanes;
use App\Modules\Desarrollo\Application\Services\ConstructorDePlan;
use App\Modules\Desarrollo\Domain\Exceptions\NombreDePlanDuplicado;
use App\Modules\Desarrollo\Domain\Exceptions\PlanNoEditable;
use App\Modules\Desarrollo\Domain\Exceptions\PlanNoEncontrado;
use App\Modules\Desarrollo\Domain\Repositories\PlanRepositoryInterface;
use App\Modules\Desarrollo\Domain\Services\ReglasDePlan;
use App\Modules\Desarrollo\Domain\ValueObjects\OperacionPlan;

/** Reemplaza datos generales, aprobador y acciones de un plan en Borrador. */
final class ActualizarPlan
{
    public function __construct(
        private readonly PlanRepositoryInterface $repositorio,
        private readonly ConstructorDePlan $constructor,
        private readonly ArmadorDePlanes $armador,
        private readonly ReglasDePlan $reglas,
    ) {}

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     *
     * @throws PlanNoEncontrado
     * @throws PlanNoEditable
     * @throws NombreDePlanDuplicado
     */
    public function ejecutar(int $tenantId, int $actorId, int $planId, array $datos): array
    {
        $actual = $this->repositorio->obtener($tenantId, $planId) ?? throw new PlanNoEncontrado;
        if (! $this->reglas->permite($actual->estado, OperacionPlan::Editar)) {
            throw new PlanNoEditable;
        }

        $plan = $this->constructor->construir($tenantId, $actorId, $datos, $actual);
        if ($this->repositorio->existeNombre($tenantId, $plan->anio, $plan->nombreNormalizado, $planId)) {
            throw new NombreDePlanDuplicado;
        }

        return $this->armador->plan($this->repositorio->reemplazar($plan), $actorId);
    }
}
