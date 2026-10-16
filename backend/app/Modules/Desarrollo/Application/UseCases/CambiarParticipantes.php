<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Ports\ColaboradoresVigentesInterface;
use App\Modules\Desarrollo\Application\Ports\NombresDeReferenciaInterface;
use App\Modules\Desarrollo\Application\Ports\RelojInterface;
use App\Modules\Desarrollo\Application\Services\ArmadorDePlanes;
use App\Modules\Desarrollo\Domain\Exceptions\AccionCancelada;
use App\Modules\Desarrollo\Domain\Exceptions\AccionNoEncontrada;
use App\Modules\Desarrollo\Domain\Exceptions\ColaboradorInvalido;
use App\Modules\Desarrollo\Domain\Exceptions\CupoExcedido;
use App\Modules\Desarrollo\Domain\Exceptions\ParticipantesRepetidos;
use App\Modules\Desarrollo\Domain\Exceptions\PlanNoEditable;
use App\Modules\Desarrollo\Domain\Repositories\PlanRepositoryInterface;
use App\Modules\Desarrollo\Domain\Services\ReglasDePlan;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoPlan;
use App\Modules\Desarrollo\Domain\ValueObjects\OperacionPlan;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoHistorialPlan;
use Throwable;

/**
 * Reemplaza los participantes de una acción respetando la capacidad. Desde la aprobación cada colaborador agregado o
 * quitado queda en el historial. Los ya inscritos se conservan aunque hayan perdido el vínculo laboral y siguen
 * ocupando cupo hasta que el administrador los quite (no se les exige vínculo vigente: solo a los que se agregan).
 */
final class CambiarParticipantes
{
    public function __construct(
        private readonly PlanRepositoryInterface $repositorio,
        private readonly ReglasDePlan $reglas,
        private readonly ColaboradoresVigentesInterface $vigentes,
        private readonly NombresDeReferenciaInterface $referencias,
        private readonly RelojInterface $reloj,
        private readonly ArmadorDePlanes $armador,
    ) {}

    /**
     * @param  list<int>  $colaboradorIds
     * @return array{accion: array<string, mixed>, avisos_cruce: list<array<string, mixed>>}
     *
     * @throws AccionNoEncontrada
     * @throws PlanNoEditable
     * @throws AccionCancelada
     * @throws ParticipantesRepetidos
     * @throws CupoExcedido
     * @throws ColaboradorInvalido
     */
    public function ejecutar(int $tenantId, int $usuarioId, int $accionId, array $colaboradorIds): array
    {
        $plan = $this->repositorio->obtenerPorAccion($tenantId, $accionId) ?? throw new AccionNoEncontrada;
        $accion = $plan->accion($accionId) ?? throw new AccionNoEncontrada;

        if (! $this->reglas->permite($plan->estado, OperacionPlan::CambiarParticipantes)) {
            throw new PlanNoEditable;
        }
        if ($accion->cancelada) {
            throw new AccionCancelada;
        }

        $nuevos = array_map('intval', $colaboradorIds);
        if (count($nuevos) !== count(array_unique($nuevos))) {
            throw new ParticipantesRepetidos;
        }
        if ($accion->capacidadMaxima !== null && count($nuevos) > $accion->capacidadMaxima) {
            throw new CupoExcedido;
        }
        $agregados = array_values(array_diff($nuevos, $accion->colaboradorIds));
        $quitados = array_values(array_diff($accion->colaboradorIds, $nuevos));
        if ($agregados !== [] && count($this->vigentes->vigentesPorIds($tenantId, $agregados, $this->reloj->hoy($usuarioId)->format('Y-m-d'))) !== count($agregados)) {
            throw new ColaboradorInvalido;
        }

        $registrar = in_array($plan->estado, [EstadoPlan::Aprobado, EstadoPlan::Ejecutando], true);
        $nombres = $registrar ? $this->nombres($tenantId, array_merge($agregados, $quitados)) : [];
        $this->repositorio->transaccion(function () use ($accionId, $plan, $nuevos, $agregados, $quitados, $usuarioId, $registrar, $nombres): void {
            $this->repositorio->reemplazarParticipantes($accionId, $nuevos, $usuarioId);
            if (! $registrar) {
                return;
            }
            foreach ($agregados as $id) {
                $this->repositorio->registrarHistorial((int) $plan->id, $accionId, TipoHistorialPlan::Participante, null, $nombres[$id] ?? 'No disponible', null, $usuarioId);
            }
            foreach ($quitados as $id) {
                $this->repositorio->registrarHistorial((int) $plan->id, $accionId, TipoHistorialPlan::Participante, $nombres[$id] ?? 'No disponible', null, null, $usuarioId);
            }
        });

        $actualizado = $this->armador->plan($this->repositorio->obtener($tenantId, (int) $plan->id) ?? $plan, $usuarioId);

        return [
            'accion' => collect($actualizado['acciones'])->firstWhere('id', $accionId) ?? [],
            'avisos_cruce' => array_values(array_filter($actualizado['avisos_cruce'], fn (array $a) => $a['accion_id'] === $accionId)),
        ];
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private function nombres(int $tenantId, array $ids): array
    {
        try {
            return $this->referencias->colaboradores($tenantId, $ids);
        } catch (Throwable) {
            return [];
        }
    }
}
