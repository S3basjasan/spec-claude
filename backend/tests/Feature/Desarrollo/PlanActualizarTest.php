<?php

use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PlanAccionCompetenciaModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PlanAccionModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PlanAccionParticipanteModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PlanModel;

beforeEach(function () {
    $this->falsos = planFalsos();
    $this->admin = actuarComoAdminDeTenant();
    $this->falsos['aprobadores']->administradores[$this->admin->id] = $this->admin->name;
});

it('reemplaza datos, acciones, competencias y participantes de un Borrador', function () {
    $plan = planGuardado(['acciones' => [cuerpoAccion(['nombre' => 'A']), cuerpoAccion(['nombre' => 'B', 'competencia_ids' => [2]])]]);
    $a = $plan['acciones'][0]['id'];

    $cuerpo = cuerpoPlan([
        'nombre' => 'Plan renovado',
        'aprobador_id' => $this->admin->id,
        'acciones' => [
            cuerpoAccion(['id' => $a, 'nombre' => 'A editada', 'competencia_ids' => [1, 3], 'colaborador_ids' => [12]]),
            cuerpoAccion(['nombre' => 'C nueva', 'colaborador_ids' => []]),
        ],
    ]);
    $respuesta = $this->putJson('/api/planes/'.$plan['id'], $cuerpo)->assertOk();

    $respuesta->assertJsonPath('data.nombre', 'Plan renovado')
        ->assertJsonPath('data.aprobador.id', $this->admin->id)
        ->assertJsonPath('data.acciones.0.id', $a)
        ->assertJsonPath('data.acciones.0.nombre', 'A editada')
        ->assertJsonCount(2, 'data.acciones.0.competencias')
        ->assertJsonCount(1, 'data.acciones.0.participantes')
        ->assertJsonPath('data.acciones.1.nombre', 'C nueva');
    expect(PlanModel::query()->count())->toBe(1)
        ->and(PlanAccionModel::query()->pluck('nombre')->all())->toBe(['A editada', 'C nueva'])
        ->and(PlanAccionCompetenciaModel::query()->count())->toBe(3)
        ->and(PlanAccionParticipanteModel::query()->pluck('colaborador_id')->map(fn ($i) => (int) $i)->all())->toBe([12]);
});

it('no deja filas huérfanas al quitar una acción', function () {
    $plan = planGuardado(['acciones' => [cuerpoAccion(['nombre' => 'A']), cuerpoAccion(['nombre' => 'B'])]]);

    $this->putJson('/api/planes/'.$plan['id'], cuerpoPlan(['acciones' => []]))->assertOk()->assertJsonCount(0, 'data.acciones');

    expect(PlanAccionModel::query()->count())->toBe(0)
        ->and(PlanAccionCompetenciaModel::query()->count())->toBe(0)
        ->and(PlanAccionParticipanteModel::query()->count())->toBe(0);
});

it('conserva una competencia que luego se desactivó, pero no permite añadir otra inactiva', function () {
    $plan = planGuardado(['acciones' => [cuerpoAccion(['competencia_ids' => [1, 2]])]]);
    $a = $plan['acciones'][0]['id'];
    planFalsos(['inactivas' => [2, 9], 'competencias' => [1, 3]]);
    $this->falsos['aprobadores']->administradores[$this->admin->id] = $this->admin->name;

    $this->putJson('/api/planes/'.$plan['id'], cuerpoPlan(['acciones' => [cuerpoAccion(['id' => $a, 'competencia_ids' => [1, 2]])]]))
        ->assertOk()->assertJsonPath('data.acciones.0.competencias.1.activa', false);

    $this->putJson('/api/planes/'.$plan['id'], cuerpoPlan(['acciones' => [cuerpoAccion(['id' => $a, 'competencia_ids' => [1, 2, 9]])]]))
        ->assertUnprocessable()->assertJsonPath('code', 'competencia_invalida');
});

it('no cuenta el propio nombre como duplicado pero sí el de otro plan', function () {
    $uno = planGuardado(['nombre' => 'Plan uno']);
    planGuardado(['nombre' => 'Plan dos']);

    $this->putJson('/api/planes/'.$uno['id'], cuerpoPlan(['nombre' => ' PLAN UNO ']))->assertOk();
    $this->putJson('/api/planes/'.$uno['id'], cuerpoPlan(['nombre' => 'plan dos']))
        ->assertUnprocessable()->assertJsonPath('code', 'nombre_duplicado');
});

it('rechaza actualizar un plan que no está en Borrador', function (string $estado) {
    $plan = planGuardado();
    forzarEstadoPlan($plan['id'], $estado);

    $this->putJson('/api/planes/'.$plan['id'], cuerpoPlan(['nombre' => 'Cambio']))
        ->assertUnprocessable()->assertJsonPath('code', 'plan_no_editable');
    expect(PlanModel::query()->first()->nombre)->toBe('Plan técnico 2026');
})->with(['Revision', 'Aprobado', 'Ejecutando', 'Cerrado']);

it('responde 404 para un plan inexistente o de otro tenant', function () {
    $plan = planGuardado();
    $this->putJson('/api/planes/9999', cuerpoPlan())->assertNotFound();

    actuarComoAdminDeTenant(2);
    $this->putJson('/api/planes/'.$plan['id'], cuerpoPlan())->assertNotFound();
});
