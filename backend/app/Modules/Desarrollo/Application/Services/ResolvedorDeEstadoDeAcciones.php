<?php

namespace App\Modules\Desarrollo\Application\Services;

use App\Modules\Desarrollo\Application\Ports\AccionesVinculablesInterface;
use App\Modules\Desarrollo\Application\Ports\EstadoFormativoDeAccionInterface;
use App\Modules\Desarrollo\Domain\Entities\AccionDePdi;
use App\Modules\Desarrollo\Domain\Entities\Pdi;
use App\Modules\Desarrollo\Domain\Services\DerivadorDeEstadoDeAccion;
use App\Modules\Desarrollo\Domain\ValueObjects\AccionDeActividad;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDeAccionPdi;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDePdi;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoPlan;
use DateTimeImmutable;
use Throwable;

/**
 * Resuelve el estado visible de cada acción de un plan. Sin vínculo es el estado registrado a mano. Con vínculo se deriva
 * del Plan de Formación y de la asistencia mientras el plan no esté Cerrado; al cerrar queda el estado guardado
 * (FR-020, FR-020a). Una sola lectura de acciones y una de asistencias por plan (sin N+1).
 */
final class ResolvedorDeEstadoDeAcciones
{
    public function __construct(
        private readonly AccionesVinculablesInterface $acciones,
        private readonly EstadoFormativoDeAccionInterface $formativo,
        private readonly DerivadorDeEstadoDeAccion $derivador,
    ) {}

    /**
     * @return array<int, array{estado: EstadoDeAccionPdi, derivado: bool, requiere_revision: bool, vinculo: array{plan_accion_id: int, plan_codigo: string|null, plan_nombre: string|null, accion_nombre: string|null, actividad_id: int|null, requiere_revision: bool}|null}>
     *                                                                                                                                                                                                                                                                          accion_id => estado resuelto
     */
    public function resolver(Pdi $pdi, DateTimeImmutable $hoy): array
    {
        $vinculadas = array_values(array_filter($pdi->acciones(), fn (AccionDePdi $a) => $a->estaVinculada()));
        $congelado = $pdi->estado === EstadoDePdi::Cerrado;
        $planes = [];
        $formativo = [];

        if ($vinculadas !== []) {
            $ids = array_values(array_unique(array_map(fn (AccionDePdi $a) => (int) $a->planAccionId, $vinculadas)));
            $planes = $this->seguro(fn () => $this->acciones->porIds($pdi->tenantId, $ids), null);
            if (! $congelado) {
                $formativo = $this->seguro(fn () => $this->formativo->deColaborador($pdi->tenantId, $pdi->colaboradorId, $ids), []);
            }
        }

        $resueltas = [];
        foreach ($pdi->acciones() as $accion) {
            $resueltas[(int) $accion->id] = $accion->estaVinculada()
                ? $this->vinculada($pdi, $accion, $planes, $formativo, $hoy, $congelado)
                : ['estado' => $accion->estado, 'derivado' => false, 'requiere_revision' => false, 'vinculo' => null];
        }

        return $resueltas;
    }

    /**
     * @param  array<int, AccionDeActividad>|null  $planes
     * @param  array<int, array{actividad_id: int, estado: string, asistencia: string}>  $formativo
     * @return array<string, mixed>
     */
    private function vinculada(Pdi $pdi, AccionDePdi $accion, ?array $planes, array $formativo, DateTimeImmutable $hoy, bool $congelado): array
    {
        $id = (int) $accion->planAccionId;
        $delPlan = $planes[$id] ?? null;
        $fuenteCaida = $planes === null;
        $revision = ! $congelado && ! $fuenteCaida && ($delPlan === null || ! in_array($pdi->colaboradorId, $delPlan->colaboradorIds, true) || ! $this->planVigente($delPlan->planEstado));
        $fila = $formativo[$id] ?? null;

        $estado = $congelado || $fuenteCaida
            ? $accion->estado
            : $this->derivador->derivar($delPlan?->cancelada ?? false, $fila['estado'] ?? null, $fila['asistencia'] ?? null, $hoy, $accion->fechaInicio, $accion->fechaFin);

        return [
            'estado' => $estado,
            'derivado' => ! $congelado,
            'requiere_revision' => $revision,
            'vinculo' => [
                'plan_accion_id' => $id,
                'plan_codigo' => $delPlan?->planCodigo,
                'plan_nombre' => $delPlan?->planNombre,
                'accion_nombre' => $delPlan?->nombre,
                'actividad_id' => $fila['actividad_id'] ?? null,
                'requiere_revision' => $revision,
            ],
        ];
    }

    private function planVigente(EstadoPlan $estado): bool
    {
        return in_array($estado, [EstadoPlan::Aprobado, EstadoPlan::Ejecutando, EstadoPlan::Cerrado], true);
    }

    /**
     * @template T
     *
     * @param  callable(): T  $fuente
     * @param  T  $porDefecto
     * @return T
     */
    private function seguro(callable $fuente, mixed $porDefecto): mixed
    {
        try {
            return $fuente();
        } catch (Throwable) {
            return $porDefecto;
        }
    }
}
