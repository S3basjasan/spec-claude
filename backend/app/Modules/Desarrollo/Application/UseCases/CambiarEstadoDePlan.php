<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Ports\RelojInterface;
use App\Modules\Desarrollo\Application\Services\ArmadorDePlanes;
use App\Modules\Desarrollo\Domain\Entities\AccionFormativa;
use App\Modules\Desarrollo\Domain\Entities\Plan;
use App\Modules\Desarrollo\Domain\Exceptions\AccionesSinFinalizar;
use App\Modules\Desarrollo\Domain\Exceptions\MotivoRequerido;
use App\Modules\Desarrollo\Domain\Exceptions\PlanCambioDeEstado;
use App\Modules\Desarrollo\Domain\Exceptions\PlanIncompleto;
use App\Modules\Desarrollo\Domain\Exceptions\PlanNoEncontrado;
use App\Modules\Desarrollo\Domain\Exceptions\TransicionInvalida;
use App\Modules\Desarrollo\Domain\Exceptions\UsuarioNoEsAprobador;
use App\Modules\Desarrollo\Domain\Repositories\PlanRepositoryInterface;
use App\Modules\Desarrollo\Domain\Services\CalculadorDeEstadoDeAccion;
use App\Modules\Desarrollo\Domain\Services\ReglasDePlan;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoAccion;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoPlan;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoHistorialPlan;
use DateTimeImmutable;

/**
 * Aplica una transición del ciclo de vida del plan y la registra en el historial. Las reglas viven en
 * `ReglasDePlan`; aquí se comprueba quién la hace y se evita la carrera con un cambio condicionado.
 */
final class CambiarEstadoDePlan
{
    public function __construct(
        private readonly PlanRepositoryInterface $repositorio,
        private readonly ReglasDePlan $reglas,
        private readonly CalculadorDeEstadoDeAccion $calculador,
        private readonly RelojInterface $reloj,
        private readonly ArmadorDePlanes $armador,
    ) {}

    /**
     * @return array<string, mixed>
     *
     * @throws PlanNoEncontrado
     * @throws TransicionInvalida
     * @throws UsuarioNoEsAprobador
     * @throws MotivoRequerido
     * @throws PlanIncompleto
     * @throws AccionesSinFinalizar
     * @throws PlanCambioDeEstado
     */
    public function ejecutar(int $tenantId, int $usuarioId, int $planId, EstadoPlan $destino, ?string $motivo): array
    {
        $plan = $this->repositorio->obtener($tenantId, $planId) ?? throw new PlanNoEncontrado;
        $origen = $plan->estado;

        if (! $this->reglas->transicionPermitida($origen, $destino)) {
            throw new TransicionInvalida;
        }

        $motivo = $motivo === null || trim($motivo) === '' ? null : trim($motivo);
        $decideAprobador = $origen === EstadoPlan::Revision;
        if ($decideAprobador && $plan->aprobadorId !== $usuarioId) {
            throw new UsuarioNoEsAprobador;
        }
        if ($origen === EstadoPlan::Revision && $destino === EstadoPlan::Borrador && $motivo === null) {
            throw new MotivoRequerido;
        }
        if ($destino === EstadoPlan::Revision) {
            $faltantes = $this->reglas->faltantes($plan);
            if ($faltantes !== []) {
                throw new PlanIncompleto($faltantes);
            }
        }
        if ($destino === EstadoPlan::Cerrado && ! $this->reglas->puedeCerrar($this->estadosDeAcciones($plan, $usuarioId))) {
            throw new AccionesSinFinalizar;
        }

        $aprobadoEn = $destino === EstadoPlan::Aprobado ? new DateTimeImmutable : null;
        $this->repositorio->transaccion(function () use ($tenantId, $planId, $origen, $destino, $aprobadoEn, $usuarioId, $motivo): void {
            if (! $this->repositorio->cambiarEstadoSi($tenantId, $planId, $origen, $destino, $aprobadoEn, $usuarioId)) {
                throw new PlanCambioDeEstado;
            }
            $this->repositorio->registrarHistorial($planId, null, TipoHistorialPlan::Estado, $origen->value, $destino->value, $motivo, $usuarioId);
        });

        return $this->armador->plan($this->repositorio->obtener($tenantId, $planId) ?? throw new PlanNoEncontrado, $usuarioId);
    }

    /** @return list<EstadoAccion> */
    private function estadosDeAcciones(Plan $plan, int $usuarioId): array
    {
        $hoy = $this->reloj->hoy($usuarioId);

        return array_map(
            fn (AccionFormativa $a) => $this->calculador->calcular($a->cancelada, $a->fechaInicio, $a->fechaFin, $hoy),
            $plan->acciones,
        );
    }
}
