<?php

use App\Modules\Desarrollo\Domain\Repositories\DnfRepositoryInterface;
use App\Modules\Desarrollo\Presentation\Facades\DesarrolloFacade;

beforeEach(function () {
    $this->falsos = actividadFalsos(['hoy' => '2026-10-17', 'vigentes' => [10 => 'Ana', 11 => 'Luis']]);
});

it('planesConAcciones conserva su forma anterior y suma nombre, competencias y participantes', function () {
    [$plan, $accion] = accionEnPlan(['nombre' => 'Excel avanzado', 'competencia_ids' => [1, 2], 'colaborador_ids' => [10, 11]]);

    $planes = DesarrolloFacade::getPlanesYAcciones(1, ['plan_id' => $plan]);

    expect($planes)->toHaveCount(1)
        ->and($planes[0])->toHaveKeys(['id', 'codigo', 'nombre', 'anio', 'estado', 'costo_total', 'horas_totales', 'acciones'])
        ->and($planes[0]['acciones'][0])->toMatchArray([
            'id' => $accion, 'nombre' => 'Excel avanzado', 'tipo' => 'Presencial', 'horas' => 16.0, 'costo' => 800.0, 'cancelada' => false, 'inscritos' => 2,
        ])
        ->and($planes[0]['acciones'][0]['competencia_ids'])->toEqualCanonicalizing([1, 2])
        ->and($planes[0]['acciones'][0]['participante_ids'])->toEqualCanonicalizing([10, 11]);
});

it('evaluacionesDeCompetencias entrega estado y niveles ordenados por fecha y DNF', function () {
    $segunda = dnfEvaluada('2026-06-10', 10, 3, 'Cumple', 'B');
    $primera = dnfEvaluada('2026-01-10', 10, 3, 'ConBrecha', 'D');
    dnfEvaluada('2026-03-10', 11, 4, 'SinEvaluacion', null);

    $filas = app(DnfRepositoryInterface::class)->evaluacionesDeCompetencias(1, []);

    expect(array_column($filas, 'dnf_id'))->toBe([$primera, $filas[1]['dnf_id'], $segunda])
        ->and($filas[0])->toBe([
            'dnf_id' => $primera, 'fecha' => '2026-01-10', 'colaborador_id' => 10, 'competencia_id' => 3,
            'estado' => 'ConBrecha', 'nivel_requerido' => 'B', 'nivel_actual' => 'D',
        ])
        ->and($filas[1]['estado'])->toBe('SinEvaluacion')->and($filas[1]['nivel_actual'])->toBeNull();
});

it('evaluacionesDeCompetencias filtra por fechas y por colaborador', function () {
    dnfEvaluada('2026-01-10', 10, 3, 'ConBrecha', 'D');
    dnfEvaluada('2026-06-10', 10, 3, 'Cumple', 'B');
    dnfEvaluada('2026-06-11', 11, 3, 'ConBrecha', 'C');
    $repo = app(DnfRepositoryInterface::class);

    expect($repo->evaluacionesDeCompetencias(1, ['fecha_desde' => '2026-06-01']))->toHaveCount(2)
        ->and($repo->evaluacionesDeCompetencias(1, ['fecha_hasta' => '2026-02-01']))->toHaveCount(1)
        ->and($repo->evaluacionesDeCompetencias(1, ['colaborador_id' => 11]))->toHaveCount(1)
        ->and($repo->evaluacionesDeCompetencias(1, ['colaborador_ids' => [10, 11]]))->toHaveCount(3)
        ->and($repo->evaluacionesDeCompetencias(1, ['colaborador_ids' => []]))->toBe([]);
});

it('evaluacionesDeCompetencias aísla por tenant', function () {
    dnfEvaluada('2026-01-10', 10, 3, 'ConBrecha', 'D', tenantId: 2);

    expect(app(DnfRepositoryInterface::class)->evaluacionesDeCompetencias(1, []))->toBe([])
        ->and(app(DnfRepositoryInterface::class)->evaluacionesDeCompetencias(2, []))->toHaveCount(1);
});
