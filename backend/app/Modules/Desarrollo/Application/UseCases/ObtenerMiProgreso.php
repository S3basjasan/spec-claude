<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Ports\AccionesDePlanInterface;
use App\Modules\Desarrollo\Application\Ports\CuentaColaboradorInterface;
use App\Modules\Desarrollo\Application\Ports\FuenteDeActividadesInterface;
use App\Modules\Desarrollo\Application\Ports\FuenteDeDnfInterface;
use App\Modules\Desarrollo\Application\Ports\FuenteDeMicrolearningInterface;
use App\Modules\Desarrollo\Application\Ports\NombresDeReferenciaInterface;
use App\Modules\Desarrollo\Application\Ports\RelojInterface;
use App\Modules\Desarrollo\Domain\Repositories\ReportesRepositoryInterface;
use App\Modules\Desarrollo\Domain\Services\CalculadorDeBrechasCerradas;
use App\Modules\Desarrollo\Domain\Services\ReglasDeEncuesta;
use App\Modules\Desarrollo\Domain\Services\ReglasDeSeguimiento;
use App\Modules\Desarrollo\Domain\ValueObjects\AccionDeActividad;
use App\Modules\Desarrollo\Domain\ValueObjects\AsistenciaParticipante;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoAccion;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoPlan;
use DateTimeImmutable;
use Illuminate\Support\Facades\Log;
use Throwable;

/** «Mi progreso» del colaborador autenticado: solo sus datos. Sin datos devuelve una respuesta vacía, no un error. */
final class ObtenerMiProgreso
{
    public function __construct(
        private readonly CuentaColaboradorInterface $cuentas,
        private readonly FuenteDeActividadesInterface $actividades,
        private readonly FuenteDeMicrolearningInterface $microlearning,
        private readonly FuenteDeDnfInterface $dnf,
        private readonly AccionesDePlanInterface $acciones,
        private readonly NombresDeReferenciaInterface $nombres,
        private readonly ReportesRepositoryInterface $reportes,
        private readonly CalculadorDeBrechasCerradas $brechas,
        private readonly ReglasDeEncuesta $reglasEncuesta,
        private readonly ReglasDeSeguimiento $reglasSeguimiento,
        private readonly RelojInterface $reloj,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function ejecutar(int $usuarioId): array
    {
        $cuenta = $this->cuentas->cuentaDe($usuarioId);
        if ($cuenta === null) {
            return $this->vacio();
        }
        $tenantId = $cuenta['tenant_id'];
        $colaboradorId = $cuenta['colaborador_id'];
        $hoy = $this->reloj->hoy($usuarioId);
        $caidas = [];
        $leer = function (string $fuente, callable $lectura) use (&$caidas): array {
            try {
                return $lectura();
            } catch (Throwable $e) {
                Log::warning('Fuente de «Mi progreso» no disponible: '.$fuente, ['error' => $e->getMessage()]);
                $caidas[] = $fuente;

                return [];
            }
        };

        $asistencias = $leer('actividades', fn () => $this->actividades->asistencias($tenantId, $hoy, ['colaborador_id' => $colaboradorId]));
        $porActividad = [];
        foreach ($leer('actividades', fn () => $this->actividades->actividades($tenantId, $hoy)) as $a) {
            $porActividad[$a['actividad_id']] = $a;
        }
        $completaciones = $leer('microlearning', fn () => $this->microlearning->completaciones($tenantId, ['colaborador_id' => $colaboradorId]));
        $evaluaciones = $leer('dnf', fn () => $this->dnf->evaluaciones($tenantId, ['colaborador_id' => $colaboradorId]));
        $proximas = $leer('plan', fn () => $this->acciones->deColaborador($tenantId, $colaboradorId));

        $presentes = array_values(array_filter($asistencias, fn (array $r) => $r['asistencia'] === AsistenciaParticipante::Presente->value));
        $finalizadasPresentes = array_values(array_filter($presentes, fn (array $r) => ($porActividad[$r['actividad_id']]['estado'] ?? null) === EstadoAccion::Finalizada->value));

        $horas = array_sum(array_map(fn (array $r) => (float) ($r['horas'] ?? 0), $presentes))
            + array_sum(array_map(fn (array $c) => $c['duracion_minutos'] / 60, $completaciones));
        $cerradas = array_values(array_filter($this->brechas->brechas($evaluaciones), fn (array $b) => $b['cerrada_en'] !== null));
        $evolucion = $this->brechas->evolucion($evaluaciones);

        $nombres = $this->nombresDeCompetencias($evolucion, $proximas);

        return [
            'resumen' => [
                'formaciones_completadas' => count($finalizadasPresentes) + count($completaciones),
                'competencias_adquiridas' => count($cerradas),
                'horas_acumuladas' => round($horas, 1),
                'certificados' => count($completaciones),
            ],
            'evolucion_competencias' => array_map(fn (array $e) => $e + ['competencia' => $nombres[$e['competencia_id']] ?? 'Competencia '.$e['competencia_id']], $evolucion),
            'certificados' => array_map(fn (array $c) => [
                'microcontenido_id' => $c['microcontenido_id'],
                'titulo' => $c['titulo'],
                'fecha' => substr((string) $c['fecha_completado'], 0, 10),
                'descarga' => '/api/microcontenidos/'.$c['microcontenido_id'].'/certificado',
            ], $completaciones),
            'proximas_acciones' => $this->proximas($proximas, $hoy, $nombres),
            'pendientes' => $this->pendientes($tenantId, $colaboradorId, $finalizadasPresentes, $porActividad, $hoy),
            'fuentes_no_disponibles' => array_values(array_unique($caidas)),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $finalizadasPresentes
     * @param  array<int, array<string, mixed>>  $porActividad
     * @return array{encuestas: list<array<string, mixed>>, seguimientos: list<array<string, mixed>>}
     */
    private function pendientes(int $tenantId, int $colaboradorId, array $finalizadasPresentes, array $porActividad, DateTimeImmutable $hoy): array
    {
        $respondidas = [];
        foreach ($this->reportes->encuestasDeColaborador($tenantId, $colaboradorId) as $e) {
            $respondidas[$e->actividadId] = true;
        }
        $hitos = [];
        foreach ($this->reportes->seguimientosDeColaborador($tenantId, $colaboradorId) as $s) {
            $hitos[$s->actividadId][] = $s->hito;
        }

        $encuestas = [];
        $seguimientos = [];
        foreach ($finalizadasPresentes as $r) {
            $a = $porActividad[$r['actividad_id']];
            if ($a['fecha_fin'] === null) {
                continue;
            }
            $fin = new DateTimeImmutable($a['fecha_fin']);
            $base = ['actividad_id' => $a['actividad_id'], 'codigo' => $a['codigo'], 'nombre' => $a['nombre'], 'fecha_fin' => $a['fecha_fin']];

            if (! isset($respondidas[$a['actividad_id']])
                && $this->reglasEncuesta->motivoDeRechazo(EstadoAccion::Finalizada, AsistenciaParticipante::Presente, $fin, $hoy, false) === null) {
                $encuestas[] = $base + ['vence' => $this->reglasEncuesta->vence($fin)->format('Y-m-d')];
            }
            foreach ($this->reglasSeguimiento->pendientes($fin, $hoy, $hitos[$a['actividad_id']] ?? []) as $hito) {
                $seguimientos[] = $base + ['hito' => $hito->value, 'vence' => $hito->limiteDeRespuesta($fin)->format('Y-m-d')];
            }
        }
        usort($encuestas, fn (array $x, array $y) => $x['vence'] <=> $y['vence']);
        usort($seguimientos, fn (array $x, array $y) => [$x['vence'], $x['hito']] <=> [$y['vence'], $y['hito']]);

        return ['encuestas' => $encuestas, 'seguimientos' => $seguimientos];
    }

    /**
     * @param  list<AccionDeActividad>  $acciones
     * @param  array<int, string>  $nombres
     * @return list<array<string, mixed>>
     */
    private function proximas(array $acciones, DateTimeImmutable $hoy, array $nombres): array
    {
        $filas = [];
        foreach ($acciones as $a) {
            if ($a->cancelada || $a->planEstado !== EstadoPlan::Ejecutando || $a->fechaFin === null || $a->fechaFin->setTime(0, 0) < $hoy->setTime(0, 0)) {
                continue;
            }
            $filas[] = [
                'accion_id' => $a->accionId,
                'tipo' => $a->tipo?->value,
                'nombre' => $a->nombre,
                'fecha_inicio' => $a->fechaInicio?->format('Y-m-d'),
                'fecha_fin' => $a->fechaFin->format('Y-m-d'),
                'competencias' => array_values(array_map(fn (int $id) => $nombres[$id] ?? 'Competencia '.$id, $a->competenciaIds)),
            ];
        }
        usort($filas, fn (array $x, array $y) => [$x['fecha_inicio'] ?? '9999', $x['accion_id']] <=> [$y['fecha_inicio'] ?? '9999', $y['accion_id']]);

        return $filas;
    }

    /**
     * @param  list<array<string, mixed>>  $evolucion
     * @param  list<AccionDeActividad>  $acciones
     * @return array<int, string> competencia_id => nombre
     */
    private function nombresDeCompetencias(array $evolucion, array $acciones): array
    {
        $ids = array_column($evolucion, 'competencia_id');
        foreach ($acciones as $a) {
            array_push($ids, ...$a->competenciaIds);
        }
        $ids = array_values(array_unique($ids));
        try {
            return $ids === [] ? [] : array_map(fn (array $c) => $c['nombre'], $this->nombres->competencias($ids));
        } catch (Throwable) {
            return [];
        }
    }

    /** @return array<string, mixed> */
    private function vacio(): array
    {
        return [
            'resumen' => ['formaciones_completadas' => 0, 'competencias_adquiridas' => 0, 'horas_acumuladas' => 0.0, 'certificados' => 0],
            'evolucion_competencias' => [],
            'certificados' => [],
            'proximas_acciones' => [],
            'pendientes' => ['encuestas' => [], 'seguimientos' => []],
            'fuentes_no_disponibles' => [],
        ];
    }
}
