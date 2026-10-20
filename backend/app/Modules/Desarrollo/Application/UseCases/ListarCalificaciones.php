<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Ports\AccionesDePlanInterface;
use App\Modules\Desarrollo\Application\Services\ArmadorDeActividades;
use App\Modules\Desarrollo\Domain\Entities\Actividad;
use App\Modules\Desarrollo\Domain\Repositories\ActividadRepositoryInterface;
use App\Modules\Desarrollo\Domain\Services\CalculadorDePromedios;
use App\Modules\Desarrollo\Domain\ValueObjects\AccionDeActividad;
use App\Modules\Desarrollo\Domain\ValueObjects\FiltroCalificaciones;

/**
 * Calificaciones agregadas por actividad o por participante. Los filtros de fechas, tipo, departamento y plan se
 * aplican sobre las actividades; el promedio considera solo las calificaciones asignadas dentro de ese filtro.
 */
final class ListarCalificaciones
{
    public function __construct(
        private readonly ActividadRepositoryInterface $actividades,
        private readonly AccionesDePlanInterface $acciones,
        private readonly CalculadorDePromedios $promedios,
        private readonly ArmadorDeActividades $armador,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function ejecutar(int $tenantId, FiltroCalificaciones $filtro): array
    {
        $todas = $this->actividades->listarDelTenant($tenantId);
        $acciones = $this->acciones->porIds($tenantId, array_map(fn (Actividad $a) => $a->accionId, $todas));

        /** @var list<Actividad> $filtradas */
        $filtradas = array_values(array_filter($todas, function (Actividad $a) use ($acciones, $filtro): bool {
            $accion = $acciones[$a->accionId] ?? null;

            return $accion !== null
                && ($filtro->tipo === null || $accion->tipo === $filtro->tipo)
                && ($filtro->departamentoId === null || $a->departamentoId === $filtro->departamentoId)
                && ($filtro->planId === null || $accion->planId === $filtro->planId)
                && $this->seSuperpone($accion, $filtro->fechaDesde, $filtro->fechaHasta);
        }));
        $ids = array_map(fn (Actividad $a) => (int) $a->id, $filtradas);

        if ($filtro->agrupar === 'actividad') {
            return $this->porActividad($filtradas, $acciones, $ids, $filtro);
        }
        if ($filtro->colaboradorId !== null) {
            return $this->detalleDeParticipante($tenantId, $filtradas, $acciones, $ids, $filtro->colaboradorId);
        }

        return $this->porParticipante($tenantId, $ids, $filtro);
    }

    /**
     * @param  list<Actividad>  $filtradas
     * @param  array<int, AccionDeActividad>  $acciones
     * @param  list<int>  $ids
     * @return array<string, mixed>
     */
    private function porActividad(array $filtradas, array $acciones, array $ids, FiltroCalificaciones $filtro): array
    {
        $agregados = $this->actividades->agregadosPorActividad($ids);
        usort($filtradas, fn (Actividad $x, Actividad $y) => [$acciones[$y->accionId]->fechaInicio?->format('Y-m-d') ?? '', $x->numero] <=> [$acciones[$x->accionId]->fechaInicio?->format('Y-m-d') ?? '', $y->numero]);

        $filas = array_map(function (Actividad $a) use ($acciones, $agregados): array {
            $accion = $acciones[$a->accionId];
            $agregado = $agregados[(int) $a->id] ?? ['calificados' => 0, 'suma' => 0];

            return [
                'id' => (int) $a->id,
                'codigo' => $a->codigo(),
                'nombre' => $accion->nombre,
                'tipo' => $accion->tipo?->value,
                'fecha_inicio' => $accion->fechaInicio?->format('Y-m-d'),
                'fecha_fin' => $accion->fechaFin?->format('Y-m-d'),
                'calificados' => $agregado['calificados'],
                'promedio' => $this->promedios->promedio($agregado['suma'], $agregado['calificados']),
            ];
        }, $filtradas);

        return $this->paginar($filas, $filtro);
    }

    /**
     * @param  list<int>  $ids
     * @return array<string, mixed>
     */
    private function porParticipante(int $tenantId, array $ids, FiltroCalificaciones $filtro): array
    {
        $agregados = $this->actividades->agregadosPorColaborador($ids);
        $nombres = $this->armador->nombresDeColaboradores($tenantId, array_keys($agregados));

        $filas = [];
        foreach ($agregados as $colaboradorId => $a) {
            $filas[] = [
                'colaborador_id' => $colaboradorId,
                'nombre' => $nombres[$colaboradorId] ?? ArmadorDeActividades::NO_DISPONIBLE,
                'actividades_calificadas' => $a['calificadas'],
                'promedio' => $this->promedios->promedio($a['suma'], $a['calificadas']),
            ];
        }
        usort($filas, fn (array $x, array $y) => [mb_strtolower($x['nombre']), $x['colaborador_id']] <=> [mb_strtolower($y['nombre']), $y['colaborador_id']]);

        return $this->paginar($filas, $filtro);
    }

    /**
     * @param  list<Actividad>  $filtradas
     * @param  array<int, AccionDeActividad>  $acciones
     * @param  list<int>  $ids
     * @return array<string, mixed>
     */
    private function detalleDeParticipante(int $tenantId, array $filtradas, array $acciones, array $ids, int $colaboradorId): array
    {
        $registros = $this->actividades->registrosDeColaborador($ids, $colaboradorId);
        $agregado = $this->actividades->agregadosPorColaborador($ids)[$colaboradorId] ?? ['calificadas' => 0, 'suma' => 0];
        $nombre = $this->armador->nombresDeColaboradores($tenantId, [$colaboradorId])[$colaboradorId] ?? ArmadorDeActividades::NO_DISPONIBLE;

        $detalle = [];
        foreach ($filtradas as $a) {
            $registro = $registros[(int) $a->id] ?? null;
            if ($registro === null) {
                continue;
            }
            $accion = $acciones[$a->accionId];
            $detalle[] = [
                'id' => (int) $a->id,
                'codigo' => $a->codigo(),
                'nombre' => $accion->nombre,
                'tipo' => $accion->tipo?->value,
                'fecha_inicio' => $accion->fechaInicio?->format('Y-m-d'),
                'asistencia' => $registro->asistencia->value,
                'calificacion' => $registro->calificacion,
            ];
        }
        usort($detalle, fn (array $x, array $y) => [$y['fecha_inicio'] ?? '', $x['id']] <=> [$x['fecha_inicio'] ?? '', $y['id']]);

        return ['data' => [
            'colaborador_id' => $colaboradorId,
            'nombre' => $nombre,
            'actividades_calificadas' => $agregado['calificadas'],
            'promedio' => $this->promedios->promedio($agregado['suma'], $agregado['calificadas']),
            'actividades' => $detalle,
        ]];
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array{data: list<array<string, mixed>>, meta: array{page: int, per_page: int, total: int, last_page: int}}
     */
    private function paginar(array $filas, FiltroCalificaciones $filtro): array
    {
        $total = count($filas);

        return [
            'data' => array_slice($filas, ($filtro->pagina - 1) * $filtro->porPagina, $filtro->porPagina),
            'meta' => ['page' => $filtro->pagina, 'per_page' => $filtro->porPagina, 'total' => $total, 'last_page' => max(1, (int) ceil($total / $filtro->porPagina))],
        ];
    }

    private function seSuperpone(AccionDeActividad $accion, ?string $desde, ?string $hasta): bool
    {
        $inicio = $accion->fechaInicio?->format('Y-m-d');
        $fin = $accion->fechaFin?->format('Y-m-d');
        if ($desde !== null && $fin !== null && $fin < $desde) {
            return false;
        }

        return ! ($hasta !== null && $inicio !== null && $inicio > $hasta);
    }
}
