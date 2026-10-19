<?php

namespace App\Modules\Desarrollo\Domain\Services;

use App\Modules\Desarrollo\Domain\ValueObjects\DatosDeReportes;
use App\Modules\Desarrollo\Domain\ValueObjects\FiltroReportes;
use App\Modules\Desarrollo\Domain\ValueObjects\PeriodoReporte;

/**
 * Indicadores del dashboard, gráficos y filas de detalle de un período a partir de los datos ya recolectados.
 * Aplica los filtros (período, plan, tipo y departamento actual del colaborador) y el prorrateo de costos y beneficios
 * por la proporción de asistentes del departamento. No guarda nada: todo se calcula bajo demanda.
 */
final class CalculadorDeIndicadores
{
    /** @var list<string> */
    public const TIPOS = ['Presencial', 'Virtual', 'ELearning', 'OJT', 'Codesarrollo', 'Mentoring'];

    private const FUENTES = [
        'plan_cumplido' => [DatosDeReportes::FUENTE_PLAN],
        'total_horas' => [DatosDeReportes::FUENTE_ACTIVIDADES, DatosDeReportes::FUENTE_MICROLEARNING],
        'satisfaccion' => [DatosDeReportes::FUENTE_ACTIVIDADES],
        'brechas_cerradas' => [DatosDeReportes::FUENTE_DNF],
        'roi_estimado' => [DatosDeReportes::FUENTE_PLAN],
        'inversion_por_colaborador' => [DatosDeReportes::FUENTE_PLAN, DatosDeReportes::FUENTE_ACTIVIDADES],
        'horas_por_colaborador' => [DatosDeReportes::FUENTE_ACTIVIDADES],
        'costo_por_hora' => [DatosDeReportes::FUENTE_PLAN, DatosDeReportes::FUENTE_ACTIVIDADES],
        'horas_por_departamento' => [DatosDeReportes::FUENTE_ACTIVIDADES, DatosDeReportes::FUENTE_MICROLEARNING, DatosDeReportes::FUENTE_PERSONAL],
        'evolucion_brechas' => [DatosDeReportes::FUENTE_DNF],
        'distribucion_por_tipo' => [DatosDeReportes::FUENTE_ACTIVIDADES, DatosDeReportes::FUENTE_MICROLEARNING],
    ];

    public function __construct(
        private readonly ProrrateadorPorDepartamento $prorrateo,
        private readonly CalculadorDeRoi $roi,
        private readonly CalculadorDeBrechasCerradas $brechas,
    ) {}

    /**
     * Una fila por actividad En curso o Finalizada del período, con las métricas recalculadas para el departamento filtrado.
     *
     * @return list<array<string, mixed>>
     */
    public function filas(DatosDeReportes $d, FiltroReportes $f): array
    {
        if ($f->esMicrolearning()) {
            return [];
        }

        $registrosPorActividad = [];
        foreach ($d->asistencias as $r) {
            $registrosPorActividad[$r['actividad_id']][] = $r;
        }
        $encuestasPorActividad = [];
        foreach ($d->encuestas as $e) {
            $encuestasPorActividad[$e['actividad_id']][] = $e;
        }

        $filas = [];
        foreach ($d->actividades as $a) {
            if (! in_array($a['estado'], ['EnCurso', 'Finalizada'], true)) {
                continue;
            }
            $accion = $d->acciones[$a['accion_id']] ?? null;
            if ($accion === null || ! $this->coincide($f, $a['plan_id'], $a['tipo'], $a['fecha_fin'])) {
                continue;
            }

            $inscritos = $accion['participante_ids'];
            $inscritosDep = array_values(array_filter($inscritos, fn (int $c) => $this->enDepartamento($d, $f, $c)));
            if ($f->departamentoId !== null && $inscritosDep === []) {
                continue;
            }

            $registros = $registrosPorActividad[$a['actividad_id']] ?? [];
            $presentesTodos = array_values(array_map(fn (array $r) => $r['colaborador_id'], array_filter($registros, fn (array $r) => $r['asistencia'] === 'Presente')));
            $presentes = array_values(array_filter($presentesTodos, fn (int $c) => $this->enDepartamento($d, $f, $c)));
            $proporcion = $f->departamentoId === null ? 1.0 : $this->prorrateo->proporcion(count($presentes), count($presentesTodos));
            $horas = (float) ($a['horas'] ?? $accion['horas'] ?? 0);

            $calificaciones = [];
            foreach ($registros as $r) {
                if ($r['calificacion'] !== null && $this->enDepartamento($d, $f, $r['colaborador_id'])) {
                    $calificaciones[] = (int) $r['calificacion'];
                }
            }
            $satisfaccion = [];
            foreach ($encuestasPorActividad[$a['actividad_id']] ?? [] as $e) {
                if ($this->enDepartamento($d, $f, $e['colaborador_id'])) {
                    $satisfaccion[] = (int) $e['calificacion'];
                }
            }

            $filas[] = [
                'actividad_id' => $a['actividad_id'],
                'codigo' => $a['codigo'],
                'accion_id' => $a['accion_id'],
                'plan_id' => $a['plan_id'],
                'nombre' => $a['nombre'],
                'tipo' => $a['tipo'],
                'estado' => $a['estado'],
                'fecha_inicio' => $a['fecha_inicio'],
                'fecha_fin' => $a['fecha_fin'],
                'departamento_id' => $a['departamento_id'],
                'asistentes' => count($presentes),
                'inscritos' => $f->departamentoId === null ? count($inscritos) : count($inscritosDep),
                'horas_persona' => $horas * count($presentes),
                'costo' => $this->prorrateo->aplicar((float) ($accion['costo'] ?? 0), $proporcion),
                'calificacion_promedio' => $this->promedio($calificaciones),
                'satisfaccion_promedio' => $this->promedio($satisfaccion),
                'respuestas_satisfaccion' => count($satisfaccion),
                'presentes' => $presentes,
            ];
        }

        usort($filas, fn (array $x, array $y) => [$y['fecha_fin'] ?? '', $y['actividad_id']] <=> [$x['fecha_fin'] ?? '', $x['actividad_id']]);

        return $filas;
    }

    /**
     * @return array{indicadores: array<string, array<string, mixed>>, gestion: array<string, array<string, mixed>>, graficos: array<string, mixed>}
     */
    public function calcular(DatosDeReportes $d, FiltroReportes $f): array
    {
        $filas = $this->filas($d, $f);

        $indicadores = [
            'plan_cumplido' => $this->planCumplido($d, $f),
            'total_horas' => $this->totalHoras($d, $f, $filas),
            'satisfaccion' => $this->satisfaccion($d, $f, $filas),
            'brechas_cerradas' => $this->brechasCerradas($d, $f),
            'roi_estimado' => $this->roiEstimado($d, $f),
        ];
        $gestion = $this->gestion($d, $f, $filas);
        $graficos = [
            'horas_por_departamento' => $this->horasPorDepartamento($d, $f, $filas),
            'evolucion_brechas' => $this->brechas->serieMensual($this->evaluacionesDelDepartamento($d, $f), $f->periodo),
            'distribucion_por_tipo' => $this->distribucionPorTipo($d, $f, $filas),
        ];

        return [
            'indicadores' => $this->conDisponibilidad($d, $f, $indicadores),
            'gestion' => $this->conDisponibilidad($d, $f, $gestion),
            'graficos' => [
                'horas_por_departamento' => $this->grafico($d, $f, 'horas_por_departamento', $graficos['horas_por_departamento']),
                'evolucion_brechas' => $this->grafico($d, $f, 'evolucion_brechas', $graficos['evolucion_brechas']),
                'distribucion_por_tipo' => $this->grafico($d, $f, 'distribucion_por_tipo', $graficos['distribucion_por_tipo']),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function planCumplido(DatosDeReportes $d, FiltroReportes $f): array
    {
        $validas = 0;
        $finalizadas = 0;
        if (! $f->esMicrolearning()) {
            foreach ($d->acciones as $a) {
                if (! $this->planEjecutado($a) || $a['cancelada']) {
                    continue;
                }
                if (! $this->coincide($f, $a['plan_id'], $a['tipo'], $a['fecha_fin']) || ! $this->accionEnDepartamento($d, $f, $a)) {
                    continue;
                }
                $validas++;
                $finalizadas += $a['estado_accion'] === 'Finalizada' ? 1 : 0;
            }
        }

        return [
            'valor' => $validas === 0 ? null : (float) ($finalizadas / $validas * 100),
            'acciones_finalizadas' => $finalizadas,
            'acciones_validas' => $validas,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array<string, mixed>
     */
    private function totalHoras(DatosDeReportes $d, FiltroReportes $f, array $filas): array
    {
        $horasActividades = array_sum(array_column($filas, 'horas_persona'));
        $horasMicro = 0.0;
        $micro = 0;
        foreach ($this->completacionesEnAlcance($d, $f) as $c) {
            $horasMicro += $c['duracion_minutos'] / 60;
            $micro++;
        }
        $hayDatos = $filas !== [] || $micro > 0;

        return [
            'valor' => $hayDatos ? $horasActividades + $horasMicro : null,
            'horas_actividades' => (float) $horasActividades,
            'horas_microlearning' => $horasMicro,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array<string, mixed>
     */
    private function satisfaccion(DatosDeReportes $d, FiltroReportes $f, array $filas): array
    {
        $ids = array_flip(array_column($filas, 'actividad_id'));
        $suma = 0;
        $n = 0;
        foreach ($d->encuestas as $e) {
            if (isset($ids[$e['actividad_id']]) && $this->enDepartamento($d, $f, $e['colaborador_id'])) {
                $suma += (int) $e['calificacion'];
                $n++;
            }
        }

        return ['valor' => $n === 0 ? null : (float) ($suma / $n), 'respuestas' => $n];
    }

    /** @return array<string, mixed> */
    private function brechasCerradas(DatosDeReportes $d, FiltroReportes $f): array
    {
        $evaluaciones = $this->evaluacionesDelDepartamento($d, $f);
        if ($evaluaciones === []) {
            return ['valor' => null, 'detectadas' => 0];
        }
        $brechas = $this->brechas->brechas($evaluaciones);

        return [
            'valor' => (float) $this->brechas->cerradasEn($brechas, $f->periodo),
            'detectadas' => $this->brechas->detectadasEn($brechas, $f->periodo),
        ];
    }

    /** @return array<string, mixed> */
    private function roiEstimado(DatosDeReportes $d, FiltroReportes $f): array
    {
        $acciones = [];
        if (! $f->esMicrolearning()) {
            $presentes = $this->presentesPorAccion($d, $f);
            foreach ($d->acciones as $a) {
                if ($a['estado_accion'] !== 'Finalizada' || ! $this->planEjecutado($a) || ! isset($d->beneficios[$a['accion_id']])) {
                    continue;
                }
                if (! $this->coincide($f, $a['plan_id'], $a['tipo'], $a['fecha_fin']) || ! $this->accionEnDepartamento($d, $f, $a)) {
                    continue;
                }
                $p = $this->proporcionDeAccion($d, $f, $a, $presentes);
                $acciones[] = [
                    'beneficio' => $this->prorrateo->aplicar($d->beneficios[$a['accion_id']], $p),
                    'costo' => $this->prorrateo->aplicar((float) ($a['costo'] ?? 0), $p),
                ];
            }
        }

        return ['valor' => $this->roi->agregado($acciones), 'acciones_evaluadas' => count($acciones)];
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array<string, array<string, mixed>>
     */
    private function gestion(DatosDeReportes $d, FiltroReportes $f, array $filas): array
    {
        $costo = 0.0;
        if (! $f->esMicrolearning()) {
            $presentes = $this->presentesPorAccion($d, $f);
            foreach ($d->acciones as $a) {
                if ($a['estado_accion'] !== 'Finalizada' || $a['cancelada'] || ! $this->planEjecutado($a)) {
                    continue;
                }
                if (! $this->coincide($f, $a['plan_id'], $a['tipo'], $a['fecha_fin']) || ! $this->accionEnDepartamento($d, $f, $a)) {
                    continue;
                }
                $costo += $this->prorrateo->aplicar((float) ($a['costo'] ?? 0), $this->proporcionDeAccion($d, $f, $a, $presentes));
            }
        }

        $formados = [];
        $horas = 0.0;
        foreach ($filas as $fila) {
            if ($fila['estado'] !== 'Finalizada') {
                continue;
            }
            $horas += $fila['horas_persona'];
            foreach ($fila['presentes'] as $c) {
                $formados[$c] = true;
            }
        }
        $n = count($formados);

        return [
            'inversion_por_colaborador' => ['valor' => $n === 0 ? null : $costo / $n],
            'horas_por_colaborador' => ['valor' => $n === 0 ? null : $horas / $n],
            'costo_por_hora' => ['valor' => $horas <= 0 ? null : $costo / $horas],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return list<array{departamento_id: int|null, horas_actividades: float, horas_microlearning: float}>
     */
    private function horasPorDepartamento(DatosDeReportes $d, FiltroReportes $f, array $filas): array
    {
        $cubos = [];
        $sumar = function (?int $departamento, string $campo, float $horas) use (&$cubos): void {
            $clave = $departamento ?? 0;
            $cubos[$clave] ??= ['departamento_id' => $departamento, 'horas_actividades' => 0.0, 'horas_microlearning' => 0.0];
            $cubos[$clave][$campo] += $horas;
        };
        foreach ($filas as $fila) {
            $horasPorPersona = $fila['asistentes'] === 0 ? 0.0 : $fila['horas_persona'] / $fila['asistentes'];
            foreach ($fila['presentes'] as $c) {
                $sumar($d->departamentoDe($c), 'horas_actividades', $horasPorPersona);
            }
        }
        foreach ($this->completacionesEnAlcance($d, $f) as $c) {
            $sumar($d->departamentoDe($c['colaborador_id']), 'horas_microlearning', $c['duracion_minutos'] / 60);
        }

        $lista = array_values($cubos);
        usort($lista, fn (array $x, array $y) => [$y['horas_actividades'] + $y['horas_microlearning'], $x['departamento_id'] ?? 0] <=> [$x['horas_actividades'] + $x['horas_microlearning'], $y['departamento_id'] ?? 0]);

        return $lista;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return list<array{tipo: string, horas: float}>
     */
    private function distribucionPorTipo(DatosDeReportes $d, FiltroReportes $f, array $filas): array
    {
        $horas = array_fill_keys([...self::TIPOS, FiltroReportes::MICROLEARNING], 0.0);
        foreach ($filas as $fila) {
            if (isset($horas[$fila['tipo']])) {
                $horas[$fila['tipo']] += $fila['horas_persona'];
            }
        }
        foreach ($this->completacionesEnAlcance($d, $f) as $c) {
            $horas[FiltroReportes::MICROLEARNING] += $c['duracion_minutos'] / 60;
        }

        return array_map(fn (string $tipo, float $h) => ['tipo' => $tipo, 'horas' => $h], array_keys($horas), array_values($horas));
    }

    /** @return list<array<string, mixed>> completaciones del período de colaboradores del departamento, si el tipo las incluye */
    private function completacionesEnAlcance(DatosDeReportes $d, FiltroReportes $f): array
    {
        if ($f->tipo !== null && ! $f->esMicrolearning()) {
            return [];
        }

        return array_values(array_filter($d->completaciones, fn (array $c) => $this->enPeriodo($f->periodo, substr((string) $c['fecha_completado'], 0, 10))
            && $this->enDepartamento($d, $f, $c['colaborador_id'])));
    }

    /** @return list<array<string, mixed>> evaluaciones DNF de colaboradores del departamento (el indicador ignora tipo y plan) */
    private function evaluacionesDelDepartamento(DatosDeReportes $d, FiltroReportes $f): array
    {
        return array_values(array_filter($d->evaluaciones, fn (array $e) => $this->enDepartamento($d, $f, $e['colaborador_id'])));
    }

    /** @return array<int, array{total: int, dep: int}> accion_id => presentes (todos y del departamento) */
    private function presentesPorAccion(DatosDeReportes $d, FiltroReportes $f): array
    {
        $porAccion = [];
        foreach ($d->asistencias as $r) {
            if ($r['asistencia'] !== 'Presente') {
                continue;
            }
            $porAccion[$r['accion_id']] ??= ['total' => 0, 'dep' => 0];
            $porAccion[$r['accion_id']]['total']++;
            $porAccion[$r['accion_id']]['dep'] += $this->enDepartamento($d, $f, $r['colaborador_id']) ? 1 : 0;
        }

        return $porAccion;
    }

    /**
     * @param  array<string, mixed>  $accion
     * @param  array<int, array{total: int, dep: int}>  $presentes
     */
    private function proporcionDeAccion(DatosDeReportes $d, FiltroReportes $f, array $accion, array $presentes): float
    {
        if ($f->departamentoId === null) {
            return 1.0;
        }
        $tieneActividad = false;
        foreach ($d->actividades as $a) {
            if ($a['accion_id'] === $accion['accion_id']) {
                $tieneActividad = true;
                break;
            }
        }
        if ($tieneActividad) {
            $p = $presentes[$accion['accion_id']] ?? ['total' => 0, 'dep' => 0];

            return $this->prorrateo->proporcion($p['dep'], $p['total']);
        }
        $enDep = count(array_filter($accion['participante_ids'], fn (int $c) => $this->enDepartamento($d, $f, $c)));

        return $this->prorrateo->proporcion($enDep, count($accion['participante_ids']));
    }

    /** @param  array<string, mixed>  $accion solo cuentan las acciones de planes Ejecutando o Cerrado */
    private function planEjecutado(array $accion): bool
    {
        return in_array($accion['plan_estado'], ['Ejecutando', 'Cerrado'], true);
    }

    /** @param  array<string, mixed>  $accion */
    private function accionEnDepartamento(DatosDeReportes $d, FiltroReportes $f, array $accion): bool
    {
        if ($f->departamentoId === null) {
            return true;
        }
        foreach ($accion['participante_ids'] as $c) {
            if ($this->enDepartamento($d, $f, $c)) {
                return true;
            }
        }

        return false;
    }

    private function coincide(FiltroReportes $f, int $planId, ?string $tipo, ?string $fechaFin): bool
    {
        if ($f->planId !== null && $planId !== $f->planId) {
            return false;
        }
        if ($f->tipo !== null && ! $f->esMicrolearning() && $tipo !== $f->tipo) {
            return false;
        }

        return $this->enPeriodo($f->periodo, $fechaFin);
    }

    private function enPeriodo(PeriodoReporte $periodo, ?string $fecha): bool
    {
        return $fecha !== null && $fecha >= $periodo->desde->format('Y-m-d') && $fecha <= $periodo->hasta->format('Y-m-d');
    }

    private function enDepartamento(DatosDeReportes $d, FiltroReportes $f, int $colaboradorId): bool
    {
        return $f->departamentoId === null || $d->departamentoDe($colaboradorId) === $f->departamentoId;
    }

    /** @param  list<int>  $valores */
    private function promedio(array $valores): ?float
    {
        return $valores === [] ? null : round(array_sum($valores) / count($valores), 1);
    }

    /**
     * @param  array<string, array<string, mixed>>  $bloque
     * @return array<string, array<string, mixed>>
     */
    private function conDisponibilidad(DatosDeReportes $d, FiltroReportes $f, array $bloque): array
    {
        foreach ($bloque as $clave => $indicador) {
            $disponible = $this->disponible($d, $f, $clave);
            $bloque[$clave] = ['disponible' => $disponible] + $indicador;
            if (! $disponible) {
                $bloque[$clave]['valor'] = null;
            }
        }

        return $bloque;
    }

    /**
     * @param  list<array<string, mixed>>  $datos
     * @return array{disponible: bool, datos: list<array<string, mixed>>}
     */
    private function grafico(DatosDeReportes $d, FiltroReportes $f, string $clave, array $datos): array
    {
        $disponible = $this->disponible($d, $f, $clave);

        return ['disponible' => $disponible, 'datos' => $disponible ? $datos : []];
    }

    private function disponible(DatosDeReportes $d, FiltroReportes $f, string $clave): bool
    {
        $fuentes = self::FUENTES[$clave];
        if ($f->departamentoId !== null) {
            $fuentes[] = DatosDeReportes::FUENTE_PERSONAL;
        }

        return $d->disponible(...$fuentes);
    }
}
