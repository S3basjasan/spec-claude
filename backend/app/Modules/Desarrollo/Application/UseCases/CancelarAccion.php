<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Services\ArmadorDePlanes;
use App\Modules\Desarrollo\Domain\Exceptions\AccionNoEncontrada;
use App\Modules\Desarrollo\Domain\Exceptions\AccionYaCancelada;
use App\Modules\Desarrollo\Domain\Exceptions\MotivoRequerido;
use App\Modules\Desarrollo\Domain\Exceptions\PlanNoEditable;
use App\Modules\Desarrollo\Domain\Repositories\PlanRepositoryInterface;
use App\Modules\Desarrollo\Domain\Services\ReglasDePlan;
use App\Modules\Desarrollo\Domain\ValueObjects\OperacionPlan;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoHistorialPlan;

/** Cancela una acción de un plan Aprobado o Ejecutando, con motivo obligatorio y registro en el historial. */
final class CancelarAccion
{
    public function __construct(
        private readonly PlanRepositoryInterface $repositorio,
        private readonly ReglasDePlan $reglas,
        private readonly ArmadorDePlanes $armador,
    ) {}

    /**
     * @return array<string, mixed>
     *
     * @throws AccionNoEncontrada
     * @throws PlanNoEditable
     * @throws AccionYaCancelada
     * @throws MotivoRequerido
     */
    public function ejecutar(int $tenantId, int $usuarioId, int $accionId, ?string $motivo): array
    {
        $plan = $this->repositorio->obtenerPorAccion($tenantId, $accionId) ?? throw new AccionNoEncontrada;
        $accion = $plan->accion($accionId) ?? throw new AccionNoEncontrada;

        if (! $this->reglas->permite($plan->estado, OperacionPlan::CancelarAccion)) {
            throw new PlanNoEditable;
        }
        if ($accion->cancelada) {
            throw new AccionYaCancelada;
        }
        $motivo = $motivo === null ? '' : trim($motivo);
        if ($motivo === '') {
            throw new MotivoRequerido;
        }

        $this->repositorio->transaccion(function () use ($plan, $accionId, $motivo, $usuarioId): void {
            $this->repositorio->cancelarAccion($accionId, $motivo, $usuarioId);
            $this->repositorio->registrarHistorial((int) $plan->id, $accionId, TipoHistorialPlan::Cancelacion, null, 'Cancelada', $motivo, $usuarioId);
        });

        $actualizado = $this->armador->plan($this->repositorio->obtener($tenantId, (int) $plan->id) ?? $plan, $usuarioId);

        return collect($actualizado['acciones'])->firstWhere('id', $accionId) ?? [];
    }
}
