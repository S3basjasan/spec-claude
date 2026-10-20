<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Ports\AccionesDePlanInterface;
use App\Modules\Desarrollo\Application\Ports\CuentaColaboradorInterface;
use App\Modules\Desarrollo\Application\Services\ArmadorDeActividades;
use App\Modules\Desarrollo\Domain\Repositories\ActividadRepositoryInterface;
use App\Modules\Desarrollo\Domain\Services\CalculadorDePromedios;
use App\Modules\Desarrollo\Domain\Services\ReglasDeActividad;
use App\Modules\Desarrollo\Domain\ValueObjects\AsistenciaParticipante;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoAccion;

/**
 * Actividades del colaborador autenticado en planes Ejecutando o Cerrado, solo de acciones con actividad creada.
 * Nunca incluye las observaciones: son de uso interno del administrador.
 */
final class ObtenerMisActividades
{
    public function __construct(
        private readonly CuentaColaboradorInterface $cuentas,
        private readonly AccionesDePlanInterface $acciones,
        private readonly ActividadRepositoryInterface $actividades,
        private readonly ReglasDeActividad $reglas,
        private readonly CalculadorDePromedios $promedios,
        private readonly ArmadorDeActividades $armador,
    ) {}

    /**
     * @return array{resumen: array<string, mixed>, proximas: list<array<string, mixed>>, historial: list<array<string, mixed>>}
     */
    public function ejecutar(int $usuarioId): array
    {
        $cuenta = $this->cuentas->cuentaDe($usuarioId);
        if ($cuenta === null) {
            return $this->vacio();
        }

        $acciones = [];
        foreach ($this->acciones->deColaborador($cuenta['tenant_id'], $cuenta['colaborador_id']) as $accion) {
            $acciones[$accion->accionId] = $accion;
        }
        $actividades = $this->actividades->porAcciones($cuenta['tenant_id'], array_keys($acciones));
        if ($actividades === []) {
            return $this->vacio();
        }

        $registros = $this->actividades->registrosDeColaborador(array_map(fn ($a) => (int) $a->id, $actividades), $cuenta['colaborador_id']);
        $hoy = $this->armador->hoy($usuarioId);

        $proximas = [];
        $historial = [];
        $calificaciones = [];
        $presentes = 0;
        $registradas = 0;
        $finalizadas = 0;
        $inscritas = 0;
        foreach ($actividades as $actividad) {
            $accion = $acciones[$actividad->accionId];
            $estado = $this->reglas->estado($accion, $hoy);
            $registro = $registros[(int) $actividad->id] ?? null;
            $fila = [
                'id' => (int) $actividad->id,
                'codigo' => $actividad->codigo(),
                'nombre' => $accion->nombre,
                'tipo' => $accion->tipo?->value,
                'fecha_inicio' => $accion->fechaInicio?->format('Y-m-d'),
                'fecha_fin' => $accion->fechaFin?->format('Y-m-d'),
                'lugar_enlace' => $actividad->lugarEnlace,
                'formador' => $accion->formador,
                'estado' => $estado->value,
                'asistencia' => ($registro?->asistencia ?? AsistenciaParticipante::Pendiente)->value,
                'calificacion' => $registro?->calificacion,
            ];

            if ($estado === EstadoAccion::Programada || $estado === EstadoAccion::EnCurso) {
                $proximas[] = $fila;
            } else {
                $historial[] = $fila;
            }
            if ($estado !== EstadoAccion::Cancelada) {
                $inscritas++;
            }
            if ($estado === EstadoAccion::Finalizada) {
                $finalizadas++;
            }
            if ($registro !== null) {
                $registradas++;
                $presentes += $registro->asistencia === AsistenciaParticipante::Presente ? 1 : 0;
                if ($registro->calificacion !== null) {
                    $calificaciones[] = $registro->calificacion;
                }
            }
        }

        usort($proximas, fn (array $x, array $y) => [$x['fecha_inicio'] ?? '9999-12-31', $x['id']] <=> [$y['fecha_inicio'] ?? '9999-12-31', $y['id']]);
        usort($historial, fn (array $x, array $y) => [$y['fecha_inicio'] ?? '', $y['id']] <=> [$x['fecha_inicio'] ?? '', $x['id']]);

        return [
            'resumen' => [
                'inscritas' => $inscritas,
                'finalizadas' => $finalizadas,
                'asistencias_registradas' => $registradas,
                'presentes' => $presentes,
                'asistencia_porcentaje' => $registradas === 0 ? null : (int) round($presentes / $registradas * 100),
                'calificadas' => count($calificaciones),
                'calificacion_promedio' => $this->promedios->promedioDe($calificaciones),
            ],
            'proximas' => $proximas,
            'historial' => $historial,
        ];
    }

    /**
     * @return array{resumen: array<string, mixed>, proximas: list<array<string, mixed>>, historial: list<array<string, mixed>>}
     */
    private function vacio(): array
    {
        return [
            'resumen' => ['inscritas' => 0, 'finalizadas' => 0, 'asistencias_registradas' => 0, 'presentes' => 0, 'asistencia_porcentaje' => null, 'calificadas' => 0, 'calificacion_promedio' => null],
            'proximas' => [],
            'historial' => [],
        ];
    }
}
