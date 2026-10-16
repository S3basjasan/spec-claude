<?php

namespace App\Modules\Desarrollo\Domain\Services;

use App\Modules\Desarrollo\Domain\Entities\AccionFormativa;
use App\Modules\Desarrollo\Domain\Entities\Plan;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoAccion;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoPlan;
use App\Modules\Desarrollo\Domain\ValueObjects\OperacionPlan;

/**
 * Reglas del ciclo de vida del plan en un solo lugar: transiciones permitidas, completitud para enviar a revisión,
 * qué se puede editar en cada estado y qué botones corresponden a cada usuario.
 */
final class ReglasDePlan
{
    public function transicionPermitida(EstadoPlan $actual, EstadoPlan $destino): bool
    {
        return match ($actual) {
            EstadoPlan::Borrador => $destino === EstadoPlan::Revision,
            EstadoPlan::Revision => $destino === EstadoPlan::Aprobado || $destino === EstadoPlan::Borrador,
            EstadoPlan::Aprobado => $destino === EstadoPlan::Ejecutando,
            EstadoPlan::Ejecutando => $destino === EstadoPlan::Cerrado,
            EstadoPlan::Cerrado => false,
        };
    }

    public function permite(EstadoPlan $estado, OperacionPlan $operacion): bool
    {
        return match ($operacion) {
            OperacionPlan::Editar => $estado === EstadoPlan::Borrador,
            OperacionPlan::CambiarParticipantes => in_array($estado, [EstadoPlan::Borrador, EstadoPlan::Aprobado, EstadoPlan::Ejecutando], true),
            OperacionPlan::CancelarAccion => in_array($estado, [EstadoPlan::Aprobado, EstadoPlan::Ejecutando], true),
            OperacionPlan::ReasignarAprobador => $estado === EstadoPlan::Revision,
        };
    }

    /**
     * Datos que faltan para enviar el plan a revisión.
     *
     * @return list<array{accion_id: int|null, campo: string}>
     */
    public function faltantes(Plan $plan): array
    {
        $faltantes = [];
        if ($plan->acciones === []) {
            $faltantes[] = ['accion_id' => null, 'campo' => 'acciones'];
        }
        if ($plan->aprobadorId === null) {
            $faltantes[] = ['accion_id' => null, 'campo' => 'aprobador'];
        }
        foreach ($plan->acciones as $accion) {
            foreach ($this->camposPendientes($accion) as $campo) {
                $faltantes[] = ['accion_id' => $accion->id, 'campo' => $campo];
            }
        }

        return $faltantes;
    }

    /** @return list<string> */
    public function camposPendientes(AccionFormativa $accion): array
    {
        $pendientes = [];
        if ($accion->competenciaIds === []) {
            $pendientes[] = 'competencias';
        }
        if ($accion->tipo === null) {
            $pendientes[] = 'tipo';
        }
        if (! $accion->tieneFechas()) {
            $pendientes[] = 'fechas';
        }
        if ($accion->duracionHoras === null) {
            $pendientes[] = 'duracion_horas';
        }
        if ($accion->formador === null || trim($accion->formador) === '') {
            $pendientes[] = 'formador';
        }
        if ($accion->capacidadMaxima === null) {
            $pendientes[] = 'capacidad_maxima';
        }
        if ($accion->costo === null) {
            $pendientes[] = 'costo';
        }
        if ($accion->colaboradorIds === []) {
            $pendientes[] = 'participantes';
        }

        return $pendientes;
    }

    /** @param  list<EstadoAccion>  $estados */
    public function puedeCerrar(array $estados): bool
    {
        foreach ($estados as $estado) {
            if ($estado !== EstadoAccion::Finalizada && $estado !== EstadoAccion::Cancelada) {
                return false;
            }
        }

        return true;
    }

    /**
     * Botones que corresponden al usuario (administrador) sobre el plan.
     *
     * @return array<string, bool>
     */
    public function puede(Plan $plan, int $usuarioId): array
    {
        $esAprobador = $plan->aprobadorId !== null && $plan->aprobadorId === $usuarioId;
        $estado = $plan->estado;

        return [
            'editar' => $this->permite($estado, OperacionPlan::Editar),
            'enviar_revision' => $estado === EstadoPlan::Borrador,
            'aprobar' => $estado === EstadoPlan::Revision && $esAprobador,
            'devolver' => $estado === EstadoPlan::Revision && $esAprobador,
            'iniciar' => $estado === EstadoPlan::Aprobado,
            'cerrar' => $estado === EstadoPlan::Ejecutando,
            'reasignar_aprobador' => $this->permite($estado, OperacionPlan::ReasignarAprobador),
            'cambiar_participantes' => $this->permite($estado, OperacionPlan::CambiarParticipantes),
            'cancelar_acciones' => $this->permite($estado, OperacionPlan::CancelarAccion),
        ];
    }
}
