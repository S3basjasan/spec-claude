<?php

use App\Modules\Desarrollo\Domain\Services\CalculadorDeBrechasCerradas;
use App\Modules\Desarrollo\Domain\Services\CalculadorDeIndicadores;
use App\Modules\Desarrollo\Domain\Services\CalculadorDeRoi;
use App\Modules\Desarrollo\Domain\Services\ProrrateadorPorDepartamento;
use App\Modules\Desarrollo\Domain\ValueObjects\DatosDeReportes;
use App\Modules\Desarrollo\Domain\ValueObjects\FiltroReportes;
use App\Modules\Desarrollo\Domain\ValueObjects\PeriodoReporte;

function calculadorDeIndicadores(): CalculadorDeIndicadores
{
    return new CalculadorDeIndicadores(new ProrrateadorPorDepartamento, new CalculadorDeRoi, new CalculadorDeBrechasCerradas);
}

function accionDePrueba(int $id, array $c = []): array
{
    return $c + [
        'accion_id' => $id, 'plan_id' => 1, 'plan_estado' => 'Ejecutando', 'tipo' => 'Presencial', 'estado_accion' => 'Finalizada',
        'fecha_inicio' => '2026-03-01', 'fecha_fin' => '2026-03-10', 'horas' => 8.0, 'costo' => 1000.0, 'cancelada' => false,
        'participante_ids' => [1, 2, 3, 4], 'nombre' => 'Acción '.$id,
    ];
}

function actividadDePrueba(int $id, int $accionId, array $c = []): array
{
    return $c + [
        'actividad_id' => $id, 'codigo' => sprintf('ACT-%04d', $id), 'accion_id' => $accionId, 'plan_id' => 1, 'nombre' => 'Actividad '.$id,
        'tipo' => 'Presencial', 'estado' => 'Finalizada', 'fecha_inicio' => '2026-03-01', 'fecha_fin' => '2026-03-10', 'horas' => 8.0,
        'departamento_id' => null, 'inscritos' => 4, 'calificados' => 0, 'promedio' => null,
    ];
}

function asistenciaDePrueba(int $actividad, int $accion, int $colaborador, string $asistencia, ?int $calificacion = null): array
{
    return ['actividad_id' => $actividad, 'accion_id' => $accion, 'colaborador_id' => $colaborador, 'asistencia' => $asistencia, 'calificacion' => $calificacion];
}

function evalDePrueba(int $dnf, string $fecha, int $colaborador, string $estado): array
{
    return ['dnf_id' => $dnf, 'fecha' => $fecha, 'colaborador_id' => $colaborador, 'competencia_id' => 3, 'estado' => $estado, 'nivel_requerido' => 'B', 'nivel_actual' => null];
}

/** Escenario base 2026: tres acciones válidas (dos Finalizadas), una cancelada y una de un plan Borrador. */
function datosDePrueba(array $cambios = []): DatosDeReportes
{
    $d = $cambios + [
        'acciones' => [
            1 => accionDePrueba(1),
            2 => accionDePrueba(2, ['tipo' => 'ELearning', 'fecha_fin' => '2026-05-10', 'horas' => 4.0, 'costo' => 200.0, 'participante_ids' => [1, 2]]),
            3 => accionDePrueba(3, ['tipo' => 'Virtual', 'estado_accion' => 'EnCurso', 'fecha_fin' => '2026-11-01', 'horas' => 10.0, 'costo' => 300.0, 'participante_ids' => [1, 5]]),
            4 => accionDePrueba(4, ['tipo' => 'Mentoring', 'estado_accion' => 'Cancelada', 'cancelada' => true]),
            5 => accionDePrueba(5, ['plan_id' => 2, 'plan_estado' => 'Borrador']),
        ],
        'actividades' => [
            actividadDePrueba(10, 1),
            actividadDePrueba(11, 2, ['tipo' => 'ELearning', 'fecha_fin' => '2026-05-10', 'horas' => 4.0]),
            actividadDePrueba(12, 3, ['tipo' => 'Virtual', 'estado' => 'EnCurso', 'fecha_fin' => '2026-11-01', 'horas' => 10.0]),
        ],
        'asistencias' => [
            asistenciaDePrueba(10, 1, 1, 'Presente', 5), asistenciaDePrueba(10, 1, 2, 'Presente', 3), asistenciaDePrueba(10, 1, 3, 'Ausente'),
            asistenciaDePrueba(11, 2, 1, 'Presente'),
            asistenciaDePrueba(12, 3, 1, 'Presente'), asistenciaDePrueba(12, 3, 5, 'Presente'),
        ],
        'completaciones' => [
            ['colaborador_id' => 1, 'duracion_minutos' => 60, 'fecha_completado' => '2026-04-01 10:00:00'],
            ['colaborador_id' => 3, 'duracion_minutos' => 30, 'fecha_completado' => '2026-05-01 10:00:00'],
            ['colaborador_id' => 1, 'duracion_minutos' => 120, 'fecha_completado' => '2025-04-01 10:00:00'],
        ],
        'encuestas' => [
            ['actividad_id' => 10, 'colaborador_id' => 1, 'calificacion' => 5], ['actividad_id' => 10, 'colaborador_id' => 2, 'calificacion' => 3],
            ['actividad_id' => 11, 'colaborador_id' => 1, 'calificacion' => 4],
        ],
        'beneficios' => [1 => 3000.0],
        'evaluaciones' => [
            evalDePrueba(1, '2026-01-10', 1, 'ConBrecha'), evalDePrueba(2, '2026-06-10', 1, 'Cumple'), evalDePrueba(1, '2026-02-10', 3, 'ConBrecha'),
        ],
        'departamentoDe' => [1 => 10, 2 => 10, 3 => 20, 4 => 20, 5 => 20],
        'noDisponibles' => [],
    ];

    return new DatosDeReportes(
        new DateTimeImmutable('2026-10-17'), $d['acciones'], $d['actividades'], $d['asistencias'], $d['completaciones'],
        $d['encuestas'], $d['beneficios'], $d['evaluaciones'], $d['departamentoDe'], $d['noDisponibles'],
    );
}

function filtroDePrueba(?int $departamento = null, ?string $tipo = null, ?int $plan = null, string $desde = '2026-01-01', string $hasta = '2026-12-31'): FiltroReportes
{
    return new FiltroReportes(new PeriodoReporte(new DateTimeImmutable($desde), new DateTimeImmutable($hasta)), $departamento, $tipo, $plan);
}

it('calcula los cinco indicadores del escenario base', function () {
    $r = calculadorDeIndicadores()->calcular(datosDePrueba(), filtroDePrueba())['indicadores'];

    expect($r['plan_cumplido']['valor'])->toEqualWithDelta(66.6667, 0.001)
        ->and($r['plan_cumplido']['acciones_finalizadas'])->toBe(2)->and($r['plan_cumplido']['acciones_validas'])->toBe(3)
        ->and($r['total_horas']['horas_actividades'])->toBe(40.0)
        ->and($r['total_horas']['horas_microlearning'])->toBe(1.5)
        ->and($r['total_horas']['valor'])->toBe(41.5)
        ->and($r['satisfaccion']['valor'])->toBe(4.0)->and($r['satisfaccion']['respuestas'])->toBe(3)
        ->and($r['brechas_cerradas']['valor'])->toBe(1.0)->and($r['brechas_cerradas']['detectadas'])->toBe(2)
        ->and($r['roi_estimado']['valor'])->toBe(200.0)->and($r['roi_estimado']['acciones_evaluadas'])->toBe(1);
});

it('calcula los indicadores de gestión con las acciones Finalizadas', function () {
    $g = calculadorDeIndicadores()->calcular(datosDePrueba(), filtroDePrueba())['gestion'];

    // Costo Finalizadas 1 200; colaboradores formados distintos 2 (1 y 2); horas-persona Finalizadas 20.
    expect($g['inversion_por_colaborador']['valor'])->toBe(600.0)
        ->and($g['horas_por_colaborador']['valor'])->toBe(10.0)
        ->and($g['costo_por_hora']['valor'])->toBe(60.0);
});

it('devuelve valores nulos sin datos en el período', function () {
    $r = calculadorDeIndicadores()->calcular(datosDePrueba(), filtroDePrueba(desde: '2024-01-01', hasta: '2024-12-31'));

    expect($r['indicadores']['plan_cumplido']['valor'])->toBeNull()
        ->and($r['indicadores']['total_horas']['valor'])->toBeNull()
        ->and($r['indicadores']['satisfaccion']['valor'])->toBeNull()
        ->and($r['indicadores']['roi_estimado']['valor'])->toBeNull()
        ->and($r['gestion']['inversion_por_colaborador']['valor'])->toBeNull()
        ->and($r['gestion']['costo_por_hora']['valor'])->toBeNull();
});

it('el ROI estimado es nulo sin evaluaciones de impacto y con costo total cero', function () {
    $sin = calculadorDeIndicadores()->calcular(datosDePrueba(['beneficios' => []]), filtroDePrueba())['indicadores']['roi_estimado'];
    $gratis = calculadorDeIndicadores()->calcular(datosDePrueba([
        'acciones' => [1 => accionDePrueba(1, ['costo' => 0.0])],
        'actividades' => [actividadDePrueba(10, 1)], 'asistencias' => [asistenciaDePrueba(10, 1, 1, 'Presente')],
    ]), filtroDePrueba())['indicadores']['roi_estimado'];

    expect($sin['valor'])->toBeNull()->and($sin['acciones_evaluadas'])->toBe(0)
        ->and($gratis['valor'])->toBeNull();
});

it('con el filtro por departamento recalcula con sus participantes y prorratea costos y beneficios', function () {
    $r = calculadorDeIndicadores()->calcular(datosDePrueba(), filtroDePrueba(departamento: 20));

    // Solo el colaborador 5 (departamento 20) asistió a la actividad 12: 1 de 2 presentes.
    expect($r['indicadores']['plan_cumplido']['acciones_validas'])->toBe(2)->and($r['indicadores']['plan_cumplido']['acciones_finalizadas'])->toBe(1)
        ->and($r['indicadores']['total_horas']['horas_actividades'])->toBe(10.0)
        ->and($r['indicadores']['total_horas']['horas_microlearning'])->toBe(0.5)
        ->and($r['indicadores']['satisfaccion']['valor'])->toBeNull();

    $filas = collect(calculadorDeIndicadores()->filas(datosDePrueba(), filtroDePrueba(departamento: 20)))->keyBy('actividad_id');
    expect($filas->keys()->all())->toBe([12, 10])
        ->and($filas[12]['costo'])->toBe(150.0)->and($filas[12]['asistentes'])->toBe(1)->and($filas[12]['inscritos'])->toBe(1)
        ->and($filas[10]['costo'])->toBe(0.0)->and($filas[10]['asistentes'])->toBe(0);
});

it('prorratear no cambia el porcentaje de ROI, solo los montos', function () {
    $datos = datosDePrueba(['asistencias' => [
        asistenciaDePrueba(10, 1, 1, 'Presente'), asistenciaDePrueba(10, 1, 2, 'Presente'), asistenciaDePrueba(10, 1, 3, 'Presente'),
        asistenciaDePrueba(10, 1, 4, 'Presente'),
    ]]);

    $completo = calculadorDeIndicadores()->calcular($datos, filtroDePrueba())['indicadores']['roi_estimado']['valor'];
    $dep20 = calculadorDeIndicadores()->calcular($datos, filtroDePrueba(departamento: 20))['indicadores']['roi_estimado']['valor'];

    expect($dep20)->toEqualWithDelta($completo, 0.0001);
});

it('el filtro de tipo restringe actividades y plan cumplido y excluye micro-learning', function () {
    $r = calculadorDeIndicadores()->calcular(datosDePrueba(), filtroDePrueba(tipo: 'Presencial'));

    expect($r['indicadores']['plan_cumplido']['acciones_validas'])->toBe(1)
        ->and($r['indicadores']['total_horas']['horas_actividades'])->toBe(16.0)
        ->and($r['indicadores']['total_horas']['horas_microlearning'])->toBe(0.0);
});

it('con Micro-learning solo tienen datos las horas y la distribución', function () {
    $r = calculadorDeIndicadores()->calcular(datosDePrueba(), filtroDePrueba(tipo: 'Microlearning'));

    expect($r['indicadores']['total_horas']['valor'])->toBe(1.5)
        ->and($r['indicadores']['plan_cumplido']['valor'])->toBeNull()
        ->and($r['indicadores']['satisfaccion']['valor'])->toBeNull()
        ->and($r['indicadores']['roi_estimado']['valor'])->toBeNull()
        ->and(calculadorDeIndicadores()->filas(datosDePrueba(), filtroDePrueba(tipo: 'Microlearning')))->toBe([])
        ->and(collect($r['graficos']['distribucion_por_tipo']['datos'])->firstWhere('tipo', 'Microlearning')['horas'])->toBe(1.5);
});

it('las brechas cerradas ignoran el tipo y el plan', function () {
    $r = calculadorDeIndicadores()->calcular(datosDePrueba(), filtroDePrueba(tipo: 'Virtual', plan: 99))['indicadores']['brechas_cerradas'];

    expect($r['valor'])->toBe(1.0)->and($r['detectadas'])->toBe(2);
});

it('las brechas cerradas respetan el departamento', function () {
    $r = calculadorDeIndicadores()->calcular(datosDePrueba(), filtroDePrueba(departamento: 20))['indicadores']['brechas_cerradas'];

    expect($r['valor'])->toBe(0.0)->and($r['detectadas'])->toBe(1);
});

it('las brechas cerradas son nulas si no hay ninguna DNF', function () {
    $r = calculadorDeIndicadores()->calcular(datosDePrueba(['evaluaciones' => []]), filtroDePrueba())['indicadores']['brechas_cerradas'];

    expect($r['valor'])->toBeNull();
});

it('reparte las horas por departamento, con «sin departamento» para quien no lo tiene', function () {
    $datos = datosDePrueba([
        'completaciones' => [
            ['colaborador_id' => 1, 'duracion_minutos' => 60, 'fecha_completado' => '2026-04-01 10:00:00'],
            ['colaborador_id' => 3, 'duracion_minutos' => 30, 'fecha_completado' => '2026-05-01 10:00:00'],
            ['colaborador_id' => 99, 'duracion_minutos' => 60, 'fecha_completado' => '2026-05-01 10:00:00'],
        ],
    ]);

    $h = calculadorDeIndicadores()->calcular($datos, filtroDePrueba())['graficos']['horas_por_departamento']['datos'];

    expect($h)->toBe([
        ['departamento_id' => 10, 'horas_actividades' => 30.0, 'horas_microlearning' => 1.0],
        ['departamento_id' => 20, 'horas_actividades' => 10.0, 'horas_microlearning' => 0.5],
        ['departamento_id' => null, 'horas_actividades' => 0.0, 'horas_microlearning' => 1.0],
    ]);
});

it('la distribución por tipo siempre trae los siete tipos', function () {
    $d = calculadorDeIndicadores()->calcular(datosDePrueba(), filtroDePrueba())['graficos']['distribucion_por_tipo']['datos'];

    expect(array_column($d, 'tipo'))->toBe(['Presencial', 'Virtual', 'ELearning', 'OJT', 'Codesarrollo', 'Mentoring', 'Microlearning'])
        ->and(collect($d)->firstWhere('tipo', 'Presencial')['horas'])->toBe(16.0)
        ->and(collect($d)->firstWhere('tipo', 'OJT')['horas'])->toBe(0.0);
});

it('la evolución de brechas trae un punto por mes del período', function () {
    $serie = calculadorDeIndicadores()->calcular(datosDePrueba(), filtroDePrueba())['graficos']['evolucion_brechas']['datos'];

    expect($serie)->toHaveCount(12)
        ->and($serie[0])->toBe(['mes' => '2026-01', 'activas' => 1, 'cerradas' => 0])
        ->and($serie[5])->toBe(['mes' => '2026-06', 'activas' => 1, 'cerradas' => 1]);
});

it('marca como no disponibles los indicadores que dependen de una fuente caída y calcula el resto', function () {
    $r = calculadorDeIndicadores()->calcular(datosDePrueba(['noDisponibles' => ['microlearning']]), filtroDePrueba());

    expect($r['indicadores']['total_horas']['disponible'])->toBeFalse()->and($r['indicadores']['total_horas']['valor'])->toBeNull()
        ->and($r['graficos']['horas_por_departamento']['disponible'])->toBeFalse()->and($r['graficos']['horas_por_departamento']['datos'])->toBe([])
        ->and($r['graficos']['distribucion_por_tipo']['disponible'])->toBeFalse()
        ->and($r['indicadores']['plan_cumplido']['disponible'])->toBeTrue()->and($r['indicadores']['plan_cumplido']['valor'])->not->toBeNull()
        ->and($r['indicadores']['brechas_cerradas']['disponible'])->toBeTrue();
});

it('con el filtro por departamento todo depende de Gestión de Personal', function () {
    $r = calculadorDeIndicadores()->calcular(datosDePrueba(['noDisponibles' => ['personal']]), filtroDePrueba(departamento: 20));
    $sinFiltro = calculadorDeIndicadores()->calcular(datosDePrueba(['noDisponibles' => ['personal']]), filtroDePrueba());

    expect($r['indicadores']['plan_cumplido']['disponible'])->toBeFalse()
        ->and($sinFiltro['indicadores']['plan_cumplido']['disponible'])->toBeTrue()
        ->and($sinFiltro['graficos']['horas_por_departamento']['disponible'])->toBeFalse();
});

it('una brecha detectada antes y cerrada en el período puede dar más cerradas que detectadas', function () {
    $datos = datosDePrueba(['evaluaciones' => [evalDePrueba(1, '2025-11-10', 1, 'ConBrecha'), evalDePrueba(2, '2026-04-10', 1, 'Cumple')]]);

    $r = calculadorDeIndicadores()->calcular($datos, filtroDePrueba())['indicadores']['brechas_cerradas'];

    expect($r['valor'])->toBe(1.0)->and($r['detectadas'])->toBe(0);
});
