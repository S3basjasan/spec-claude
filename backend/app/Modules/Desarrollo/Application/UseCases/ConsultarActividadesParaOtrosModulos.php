<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Ports\AccionesDePlanInterface;
use App\Modules\Desarrollo\Application\Ports\RelojInterface;
use App\Modules\Desarrollo\Domain\Entities\Actividad;
use App\Modules\Desarrollo\Domain\Repositories\ActividadRepositoryInterface;
use App\Modules\Desarrollo\Domain\Services\CalculadorDePromedios;
use App\Modules\Desarrollo\Domain\Services\ReglasDeActividad;
use App\Modules\Desarrollo\Domain\ValueObjects\AccionDeActividad;
use App\Modules\Desarrollo\Domain\ValueObjects\AsistenciaParticipante;
use DateTimeImmutable;

/**
 * Consultas de solo lectura de Actividades para otros módulos (Plan de Formación y Reportes) a través de la Facade.
 * Nunca devuelven observaciones.
 */
final class ConsultarActividadesParaOtrosModulos
{
    public function __construct(
        private readonly ActividadRepositoryInterface $actividades,
        private readonly AccionesDePlanInterface $acciones,
        private readonly ReglasDeActividad $reglas,
        private readonly CalculadorDePromedios $promedios,
        private readonly RelojInterface $reloj,
    ) {}

    /**
     * @param  list<int>  $accionIds
     * @return list<array{accion_id: int, actividad_id: int, estado: string, asistencia: string, calificacion: int|null}>
     */
    public function asistenciaDeColaborador(int $tenantId, int $colaboradorId, array $accionIds): array
    {
        $actividades = $this->actividades->porAcciones($tenantId, $accionIds);
        if ($actividades === []) {
            return [];
        }
        $acciones = $this->acciones->porIds($tenantId, array_map(fn (Actividad $a) => $a->accionId, $actividades));
        $registros = $this->actividades->registrosDeColaborador(array_map(fn (Actividad $a) => (int) $a->id, $actividades), $colaboradorId);
        $hoy = $this->hoy([]);

        $filas = [];
        foreach ($actividades as $a) {
            $accion = $acciones[$a->accionId] ?? null;
            if ($accion === null || ! in_array($colaboradorId, $accion->colaboradorIds, true)) {
                continue;
            }
            $registro = $registros[(int) $a->id] ?? null;
            $filas[] = [
                'accion_id' => $a->accionId,
                'actividad_id' => (int) $a->id,
                'estado' => $this->reglas->estado($accion, $hoy)->value,
                'asistencia' => ($registro?->asistencia ?? AsistenciaParticipante::Pendiente)->value,
                'calificacion' => $registro?->calificacion,
            ];
        }

        return $filas;
    }

    public function tieneAsistenciaRegistrada(int $tenantId, int $accionId, int $colaboradorId): bool
    {
        $actividad = $this->actividades->obtenerPorAccion($tenantId, $accionId);

        return $actividad !== null && $this->actividades->tieneAsistencia((int) $actividad->id, $colaboradorId);
    }

    /**
     * @param  array<string, mixed>  $filtros  anio, plan_id, tipo, estado, departamento_id, competencia_id, fecha_desde, fecha_hasta, hoy
     * @return list<array<string, mixed>>
     */
    public function actividades(int $tenantId, array $filtros = []): array
    {
        $hoy = $this->hoy($filtros);
        $filas = [];
        foreach ($this->filtradas($tenantId, $filtros, $hoy) as [$actividad, $accion, $estado]) {
            $filas[] = [
                'actividad_id' => (int) $actividad->id,
                'codigo' => $actividad->codigo(),
                'accion_id' => $accion->accionId,
                'plan_id' => $accion->planId,
                'nombre' => $accion->nombre,
                'tipo' => $accion->tipo?->value,
                'estado' => $estado,
                'fecha_inicio' => $accion->fechaInicio?->format('Y-m-d'),
                'fecha_fin' => $accion->fechaFin?->format('Y-m-d'),
                'horas' => $accion->duracionHoras,
                'departamento_id' => $actividad->departamentoId,
                'inscritos' => $accion->inscritos(),
            ];
        }

        $agregados = $this->actividades->agregadosPorActividad(array_column($filas, 'actividad_id'));

        return array_map(function (array $f) use ($agregados): array {
            $a = $agregados[$f['actividad_id']] ?? ['calificados' => 0, 'suma' => 0];

            return $f + ['calificados' => $a['calificados'], 'promedio' => $this->promedios->promedio($a['suma'], $a['calificados'])];
        }, $filas);
    }

    /**
     * Solo participantes con registro: los Pendientes no se materializan.
     *
     * @param  array<string, mixed>  $filtros  los de `actividades` más colaborador_id y actividad_id
     * @return list<array<string, mixed>>
     */
    public function asistencias(int $tenantId, array $filtros = []): array
    {
        $hoy = $this->hoy($filtros);
        $porActividad = [];
        foreach ($this->filtradas($tenantId, $filtros, $hoy) as [$actividad, $accion]) {
            if (isset($filtros['actividad_id']) && (int) $filtros['actividad_id'] !== (int) $actividad->id) {
                continue;
            }
            $porActividad[(int) $actividad->id] = $accion;
        }

        $filas = [];
        foreach ($this->actividades->registrosDeActividades(array_keys($porActividad)) as $fila) {
            $accion = $porActividad[$fila['actividad_id']];
            $registro = $fila['registro'];
            if (isset($filtros['colaborador_id']) && (int) $filtros['colaborador_id'] !== $registro->colaboradorId) {
                continue;
            }
            if (! in_array($registro->colaboradorId, $accion->colaboradorIds, true)) {
                continue;
            }
            $filas[] = [
                'actividad_id' => $fila['actividad_id'],
                'accion_id' => $accion->accionId,
                'plan_id' => $accion->planId,
                'colaborador_id' => $registro->colaboradorId,
                'asistencia' => $registro->asistencia->value,
                'calificacion' => $registro->calificacion,
                'horas' => $accion->duracionHoras,
                'fecha_inicio' => $accion->fechaInicio?->format('Y-m-d'),
                'fecha_fin' => $accion->fechaFin?->format('Y-m-d'),
            ];
        }

        return $filas;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return list<array{0: Actividad, 1: AccionDeActividad, 2: string}>
     */
    private function filtradas(int $tenantId, array $filtros, DateTimeImmutable $hoy): array
    {
        $todas = $this->actividades->listarDelTenant($tenantId);
        $acciones = $this->acciones->porIds($tenantId, array_map(fn (Actividad $a) => $a->accionId, $todas));
        $desde = $filtros['fecha_desde'] ?? (isset($filtros['anio']) ? $filtros['anio'].'-01-01' : null);
        $hasta = $filtros['fecha_hasta'] ?? (isset($filtros['anio']) ? $filtros['anio'].'-12-31' : null);

        $resultado = [];
        foreach ($todas as $actividad) {
            $accion = $acciones[$actividad->accionId] ?? null;
            if ($accion === null) {
                continue;
            }
            $estado = $this->reglas->estado($accion, $hoy)->value;
            $inicio = $accion->fechaInicio?->format('Y-m-d');
            $fin = $accion->fechaFin?->format('Y-m-d');
            $coincide = (! isset($filtros['plan_id']) || $accion->planId === (int) $filtros['plan_id'])
                && (! isset($filtros['tipo']) || $accion->tipo?->value === $filtros['tipo'])
                && (! isset($filtros['estado']) || $estado === $filtros['estado'])
                && (! isset($filtros['departamento_id']) || $actividad->departamentoId === (int) $filtros['departamento_id'])
                && (! isset($filtros['competencia_id']) || in_array((int) $filtros['competencia_id'], $accion->competenciaIds, true))
                && ($desde === null || $fin === null || $fin >= $desde)
                && ($hasta === null || $inicio === null || $inicio <= $hasta);
            if ($coincide) {
                $resultado[] = [$actividad, $accion, $estado];
            }
        }

        return $resultado;
    }

    /** @param  array<string, mixed>  $filtros */
    private function hoy(array $filtros): DateTimeImmutable
    {
        return isset($filtros['hoy']) ? new DateTimeImmutable((string) $filtros['hoy']) : $this->reloj->hoy(0);
    }
}
