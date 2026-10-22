<?php

use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PlanAccionParticipanteModel;
use App\Modules\Desarrollo\Presentation\Facades\DesarrolloFacade;

beforeEach(function () {
    // Hoy = 2026-03-05.
    $this->falsos = actividadFalsos();
    $this->actividad = function (string $nombre, array $accion = [], ?int $departamento = null, array $plan = []) {
        [$planId, $accionId] = accionEnPlan(['nombre' => $nombre, 'colaborador_ids' => [10, 11, 12]] + $accion, plan: $plan);

        $lugar = ($accion['tipo'] ?? 'Presencial') === 'Virtual' ? 'https://meet.ejemplo.edu.ec/sala' : 'Sala 1';

        return [actividadDe($accionId, $lugar, $departamento)['id'], $planId, $accionId];
    };
});

it('getAsistenciaDeColaborador devuelve asistencia, calificación y Pendiente cuando no hay registro, sin observaciones', function () {
    [$a1, , $accion1] = ($this->actividad)('Con registro', ['fecha_inicio' => '2026-01-05', 'fecha_fin' => '2026-01-06']);
    [, , $accion2] = ($this->actividad)('Sin registro');
    accionEnPlan(['nombre' => 'Sin actividad']);
    registroDeAsistencia($a1, 10, 'Presente', 5, 'Nota interna');

    $filas = DesarrolloFacade::getAsistenciaDeColaborador(1, 10, [$accion1, $accion2, 99999]);

    expect($filas)->toBe([
        ['accion_id' => $accion1, 'actividad_id' => $a1, 'estado' => 'Finalizada', 'asistencia' => 'Presente', 'calificacion' => 5],
        ['accion_id' => $accion2, 'actividad_id' => $filas[1]['actividad_id'], 'estado' => 'EnCurso', 'asistencia' => 'Pendiente', 'calificacion' => null],
    ])->and(json_encode($filas))->not->toContain('observaciones');
});

it('getAsistenciaDeColaborador ignora acciones donde el colaborador no está inscrito y otros tenants', function () {
    [, , $accion] = ($this->actividad)('Ajena');

    expect(DesarrolloFacade::getAsistenciaDeColaborador(1, 77, [$accion]))->toBe([])
        ->and(DesarrolloFacade::getAsistenciaDeColaborador(2, 10, [$accion]))->toBe([])
        ->and(DesarrolloFacade::getAsistenciaDeColaborador(1, 10, []))->toBe([]);
});

it('tieneAsistenciaRegistrada distingue el registro de la ausencia de registro', function () {
    [$a, , $accion] = ($this->actividad)('Con asistencia');
    [, $sinActividad] = accionEnPlan();
    registroDeAsistencia($a, 10, 'Ausente');

    expect(DesarrolloFacade::tieneAsistenciaRegistrada(1, $accion, 10))->toBeTrue()
        ->and(DesarrolloFacade::tieneAsistenciaRegistrada(1, $accion, 11))->toBeFalse()
        ->and(DesarrolloFacade::tieneAsistenciaRegistrada(1, $sinActividad, 10))->toBeFalse()
        ->and(DesarrolloFacade::tieneAsistenciaRegistrada(2, $accion, 10))->toBeFalse();
});

it('getActividades entrega estado, horas, inscritos, calificados y promedio', function () {
    [$a1, $plan1, $accion1] = ($this->actividad)('Finalizada', ['fecha_inicio' => '2026-01-05', 'fecha_fin' => '2026-01-06', 'duracion_horas' => 12], 1);
    ($this->actividad)('Programada', ['fecha_inicio' => '2026-04-01', 'fecha_fin' => '2026-04-02', 'tipo' => 'OJT'], 2);
    registroDeAsistencia($a1, 10, 'Presente', 4);
    registroDeAsistencia($a1, 11, 'Presente', 5);

    $filas = DesarrolloFacade::getActividades(1);

    expect($filas)->toHaveCount(2)
        ->and($filas[0])->toBe([
            'actividad_id' => $a1, 'codigo' => 'ACT-0001', 'accion_id' => $accion1, 'plan_id' => $plan1, 'nombre' => 'Finalizada', 'tipo' => 'Presencial',
            'estado' => 'Finalizada', 'fecha_inicio' => '2026-01-05', 'fecha_fin' => '2026-01-06', 'horas' => 12.0, 'departamento_id' => 1, 'inscritos' => 3,
            'calificados' => 2, 'promedio' => 4.5,
        ])
        ->and($filas[1]['promedio'])->toBeNull()->and($filas[1]['calificados'])->toBe(0);
});

it('getActividades aplica los filtros y aísla por tenant', function () {
    ($this->actividad)('Presencial 2026 depto 1', [], 1);
    ($this->actividad)('Virtual', ['tipo' => 'Virtual'], 2);
    ($this->actividad)('De 2025', ['fecha_inicio' => '2025-05-05', 'fecha_fin' => '2025-05-06'], null, ['anio' => 2025]);

    $nombres = fn (array $f) => collect(DesarrolloFacade::getActividades(1, $f))->pluck('nombre')->all();

    expect($nombres(['anio' => 2026]))->toBe(['Presencial 2026 depto 1', 'Virtual'])
        ->and($nombres(['anio' => 2025]))->toBe(['De 2025'])
        ->and($nombres(['tipo' => 'Presencial', 'anio' => 2026]))->toBe(['Presencial 2026 depto 1'])
        ->and($nombres(['departamento_id' => 2]))->toBe(['Virtual'])
        ->and($nombres(['estado' => 'EnCurso', 'anio' => 2026]))->toBe(['Presencial 2026 depto 1', 'Virtual'])
        ->and($nombres(['estado' => 'Cancelada']))->toBe([])
        ->and($nombres(['competencia_id' => 1, 'anio' => 2025]))->toBe(['De 2025'])
        ->and(DesarrolloFacade::getActividades(2))->toBe([]);
});

it('getAsistencias solo devuelve participantes con registro, sin observaciones, con las horas de la acción', function () {
    [$a1, $plan1, $accion1] = ($this->actividad)('Una', ['duracion_horas' => 8]);
    [$a2] = ($this->actividad)('Dos');
    registroDeAsistencia($a1, 10, 'Presente', 4, 'Secreta');
    registroDeAsistencia($a1, 11, 'Justificado');
    registroDeAsistencia($a2, 10, 'Ausente');

    $todas = DesarrolloFacade::getAsistencias(1);

    expect($todas)->toHaveCount(3)
        ->and($todas[0])->toBe([
            'actividad_id' => $a1, 'accion_id' => $accion1, 'plan_id' => $plan1, 'colaborador_id' => 10, 'asistencia' => 'Presente', 'calificacion' => 4,
            'horas' => 8.0, 'fecha_inicio' => '2026-03-02', 'fecha_fin' => '2026-03-13',
        ])
        ->and(json_encode($todas))->not->toContain('Secreta')->not->toContain('observaciones')
        ->and(collect(DesarrolloFacade::getAsistencias(1, ['colaborador_id' => 10]))->pluck('actividad_id')->all())->toBe([$a1, $a2])
        ->and(collect(DesarrolloFacade::getAsistencias(1, ['actividad_id' => $a2]))->pluck('asistencia')->all())->toBe(['Ausente'])
        ->and(DesarrolloFacade::getAsistencias(2))->toBe([]);
});

it('getAsistencias ignora a quien ya no está inscrito', function () {
    [$a, , $accion] = ($this->actividad)('Retirado');
    registroDeAsistencia($a, 10, 'Presente');
    PlanAccionParticipanteModel::query()->where('plan_accion_id', $accion)->where('colaborador_id', 10)->delete();

    expect(DesarrolloFacade::getAsistencias(1))->toBe([]);
});
