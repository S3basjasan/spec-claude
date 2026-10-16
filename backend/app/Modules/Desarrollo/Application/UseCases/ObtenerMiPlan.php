<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Ports\AsistenciaInterface;
use App\Modules\Desarrollo\Application\Ports\CuentaColaboradorInterface;
use App\Modules\Desarrollo\Application\Ports\NombresDeReferenciaInterface;
use App\Modules\Desarrollo\Application\Ports\RelojInterface;
use App\Modules\Desarrollo\Domain\Repositories\PlanRepositoryInterface;
use App\Modules\Desarrollo\Domain\Services\CalculadorDeEstadoDeAccion;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoAccion;
use DateTimeImmutable;
use Throwable;

/**
 * Plan de formación del colaborador autenticado: sus acciones de planes Aprobado, Ejecutando o Cerrado en orden
 * cronológico, el porcentaje completado y sus tres próximas actividades. El colaborador sale siempre del usuario del
 * token. Una acción cuenta como completada cuando está Finalizada y el colaborador tiene asistencia Presente
 * (registrada por Actividades Formativas, aún no implementada: mientras tanto el avance será 0 %).
 */
final class ObtenerMiPlan
{
    public function __construct(
        private readonly PlanRepositoryInterface $repositorio,
        private readonly CuentaColaboradorInterface $cuentas,
        private readonly NombresDeReferenciaInterface $referencias,
        private readonly AsistenciaInterface $asistencia,
        private readonly CalculadorDeEstadoDeAccion $calculador,
        private readonly RelojInterface $reloj,
    ) {}

    /**
     * @return array{porcentaje_completado: int, acciones_asignadas: int, acciones_completadas: int, acciones: list<array<string, mixed>>, proximas: list<array<string, mixed>>}
     */
    public function ejecutar(int $usuarioId): array
    {
        $cuenta = $this->cuentas->cuentaDe($usuarioId);
        if ($cuenta === null) {
            return $this->vacio();
        }

        $acciones = $this->repositorio->accionesDeColaborador($cuenta['tenant_id'], $cuenta['colaborador_id']);
        if ($acciones === []) {
            return $this->vacio();
        }

        $hoy = $this->reloj->hoy($usuarioId);
        $presentes = array_flip($this->asistencia->presentesPorAccion(array_column($acciones, 'accion_id'), $cuenta['colaborador_id']));
        $nombres = $this->nombresDeCompetencias(array_values(array_unique(array_merge(...array_column($acciones, 'competencia_ids')))));

        $filas = array_map(function (array $a) use ($hoy, $presentes, $nombres): array {
            $estado = $this->calculador->calcular($a['cancelada'], $a['inicio'], $a['fin'], $hoy);

            return [
                'id' => $a['accion_id'],
                'plan' => ['id' => $a['plan_id'], 'nombre' => $a['plan_nombre'], 'anio' => $a['plan_anio']],
                'nombre' => $a['nombre'],
                'competencias' => array_map(fn (int $id) => ['id' => $id, 'nombre' => $nombres[$id]['nombre'] ?? 'No disponible'], $a['competencia_ids']),
                'tipo' => $a['tipo']?->value,
                'fecha_inicio' => $a['inicio']?->format('Y-m-d'),
                'fecha_fin' => $a['fin']?->format('Y-m-d'),
                'formador' => $a['formador'],
                'duracion_horas' => $a['duracion_horas'],
                'estado_accion' => $estado->value,
                'motivo_cancelacion' => $a['cancelada'] ? $a['motivo_cancelacion'] : null,
                'completada' => $estado === EstadoAccion::Finalizada && isset($presentes[$a['accion_id']]),
            ];
        }, $acciones);

        $asignadas = array_values(array_filter($filas, fn (array $f) => $f['estado_accion'] !== EstadoAccion::Cancelada->value));
        $completadas = count(array_filter($asignadas, fn (array $f) => $f['completada']));

        return [
            'porcentaje_completado' => $asignadas === [] ? 0 : (int) round($completadas / count($asignadas) * 100),
            'acciones_asignadas' => count($asignadas),
            'acciones_completadas' => $completadas,
            'acciones' => $filas,
            'proximas' => $this->proximas($asignadas, $hoy),
        ];
    }

    /**
     * Las tres acciones no canceladas con inicio de hoy en adelante, las más cercanas primero.
     *
     * @param  list<array<string, mixed>>  $asignadas
     * @return list<array<string, mixed>>
     */
    private function proximas(array $asignadas, DateTimeImmutable $hoy): array
    {
        $futuras = array_values(array_filter($asignadas, fn (array $f) => $f['fecha_inicio'] !== null && $f['fecha_inicio'] >= $hoy->format('Y-m-d')));
        usort($futuras, fn (array $a, array $b) => [$a['fecha_inicio'], $a['id']] <=> [$b['fecha_inicio'], $b['id']]);

        return array_slice($futuras, 0, 3);
    }

    /** @return array{porcentaje_completado: int, acciones_asignadas: int, acciones_completadas: int, acciones: list<array<string, mixed>>, proximas: list<array<string, mixed>>} */
    private function vacio(): array
    {
        return ['porcentaje_completado' => 0, 'acciones_asignadas' => 0, 'acciones_completadas' => 0, 'acciones' => [], 'proximas' => []];
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, array{codigo: string, nombre: string, activa: bool}>
     */
    private function nombresDeCompetencias(array $ids): array
    {
        try {
            return $ids === [] ? [] : $this->referencias->competencias($ids);
        } catch (Throwable) {
            return [];
        }
    }
}
