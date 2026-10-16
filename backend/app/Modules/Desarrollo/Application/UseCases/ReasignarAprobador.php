<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Ports\AprobadoresInterface;
use App\Modules\Desarrollo\Application\Ports\NombresDeUsuarioInterface;
use App\Modules\Desarrollo\Application\Services\ArmadorDePlanes;
use App\Modules\Desarrollo\Domain\Exceptions\AprobadorInvalido;
use App\Modules\Desarrollo\Domain\Exceptions\PlanNoEditable;
use App\Modules\Desarrollo\Domain\Exceptions\PlanNoEncontrado;
use App\Modules\Desarrollo\Domain\Repositories\PlanRepositoryInterface;
use App\Modules\Desarrollo\Domain\Services\ReglasDePlan;
use App\Modules\Desarrollo\Domain\ValueObjects\OperacionPlan;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoHistorialPlan;

/**
 * Reasigna el aprobador de un plan en Revisión (FR-016a). Cualquier Administrador puede hacerlo, por ejemplo si el
 * aprobador designado dejó de ser administrador. No cambia el estado del plan ni su fecha de aprobación.
 */
final class ReasignarAprobador
{
    public function __construct(
        private readonly PlanRepositoryInterface $repositorio,
        private readonly AprobadoresInterface $aprobadores,
        private readonly NombresDeUsuarioInterface $usuarios,
        private readonly ReglasDePlan $reglas,
        private readonly ArmadorDePlanes $armador,
    ) {}

    /**
     * @return array<string, mixed>
     *
     * @throws PlanNoEncontrado
     * @throws PlanNoEditable
     * @throws AprobadorInvalido
     */
    public function ejecutar(int $tenantId, int $usuarioId, int $planId, int $aprobadorId): array
    {
        $plan = $this->repositorio->obtener($tenantId, $planId) ?? throw new PlanNoEncontrado;
        if (! $this->reglas->permite($plan->estado, OperacionPlan::ReasignarAprobador)) {
            throw new PlanNoEditable;
        }
        if (! $this->aprobadores->esAdministradorActivo($tenantId, $aprobadorId)) {
            throw new AprobadorInvalido;
        }

        $nombres = $this->usuarios->nombresDe(array_filter([$plan->aprobadorId, $aprobadorId]));
        $this->repositorio->transaccion(function () use ($tenantId, $planId, $plan, $aprobadorId, $usuarioId, $nombres): void {
            $this->repositorio->cambiarAprobador($tenantId, $planId, $aprobadorId, $usuarioId);
            $this->repositorio->registrarHistorial(
                $planId,
                null,
                TipoHistorialPlan::Aprobador,
                $plan->aprobadorId === null ? null : ($nombres[$plan->aprobadorId] ?? 'No disponible'),
                $nombres[$aprobadorId] ?? 'No disponible',
                null,
                $usuarioId,
            );
        });

        return $this->armador->plan($this->repositorio->obtener($tenantId, $planId) ?? throw new PlanNoEncontrado, $usuarioId);
    }
}
