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

it('crea un plan en Borrador con código secuencial y todos sus datos', function () {
    $respuesta = crearPlanApi($this, cuerpoPlan(['descripcion' => 'Plan del año', 'aprobador_id' => $this->admin->id]))->assertCreated();

    $respuesta->assertJsonPath('data.codigo', 'PLAN-0001')
        ->assertJsonPath('data.estado', 'Borrador')
        ->assertJsonPath('data.anio', 2026)
        ->assertJsonPath('data.aprobador.id', $this->admin->id)
        ->assertJsonPath('data.cantidad_acciones', 1)
        ->assertJsonPath('data.costo_total', 800)
        ->assertJsonPath('data.horas_totales', 16)
        ->assertJsonPath('data.acciones.0.estado_accion', 'Programada')
        ->assertJsonPath('data.acciones.0.competencias.0.id', 1)
        ->assertJsonPath('data.acciones.0.participantes.0.colaborador_id', 10)
        ->assertJsonPath('data.puede.editar', true)
        ->assertJsonPath('data.puede.aprobar', false);
    expect(PlanModel::query()->count())->toBe(1)
        ->and(PlanAccionModel::query()->count())->toBe(1)
        ->and(PlanAccionCompetenciaModel::query()->count())->toBe(1)
        ->and(PlanAccionParticipanteModel::query()->count())->toBe(2)
        ->and((int) PlanModel::query()->first()->created_by)->toBe($this->admin->id);

    crearPlanApi($this, cuerpoPlan(['nombre' => 'Otro plan']))->assertCreated()->assertJsonPath('data.codigo', 'PLAN-0002');
});

it('guarda un Borrador con una acción que solo tiene nombre y competencias', function () {
    $cuerpo = cuerpoPlan(['acciones' => [['nombre' => 'Pendiente de definir', 'competencia_ids' => [2]]]]);

    $respuesta = crearPlanApi($this, $cuerpo)->assertCreated();

    $respuesta->assertJsonPath('data.acciones.0.tipo', null)
        ->assertJsonPath('data.acciones.0.completa', false)
        ->assertJsonPath('data.acciones.0.estado_accion', 'Programada');
    expect($respuesta->json('data.acciones.0.faltantes'))->toContain('tipo', 'fechas', 'participantes');
});

it('rechaza un plan sin nombre o sin año', function () {
    crearPlanApi($this, cuerpoPlan(['nombre' => '', 'anio' => null]))
        ->assertUnprocessable()->assertJsonValidationErrors(['nombre', 'anio']);
});

it('rechaza un nombre repetido en el mismo año sin distinguir mayúsculas ni espacios, y lo permite en otro año', function () {
    crearPlanApi($this, cuerpoPlan(['nombre' => 'Plan Técnico']))->assertCreated();

    crearPlanApi($this, cuerpoPlan(['nombre' => '  plan   TÉCNICO ']))
        ->assertUnprocessable()->assertJsonPath('code', 'nombre_duplicado')->assertJsonValidationErrors(['nombre']);
    crearPlanApi($this, cuerpoPlan(['nombre' => 'Plan Técnico', 'anio' => 2027, 'acciones' => []]))->assertCreated();
    expect(PlanModel::query()->count())->toBe(2);
});

it('aísla por tenant: el mismo nombre y la numeración son independientes', function () {
    crearPlanApi($this, cuerpoPlan())->assertCreated();
    $otro = actuarComoAdminDeTenant(2);
    $this->falsos['aprobadores']->administradores[$otro->id] = $otro->name;

    crearPlanApi($this, cuerpoPlan())->assertCreated()->assertJsonPath('data.codigo', 'PLAN-0001');
});

it('rechaza una acción sin competencias', function () {
    crearPlanApi($this, cuerpoPlan(['acciones' => [cuerpoAccion(['competencia_ids' => []])]]))
        ->assertUnprocessable()->assertJsonValidationErrors(['acciones.0.competencia_ids']);
});

it('rechaza competencias inactivas o inexistentes', function (int $competenciaId) {
    crearPlanApi($this, cuerpoPlan(['acciones' => [cuerpoAccion(['competencia_ids' => [1, $competenciaId]])]]))
        ->assertUnprocessable()->assertJsonPath('code', 'competencia_invalida')->assertJsonValidationErrors(['acciones.0.competencia_ids']);
})->with(['inactiva' => 9, 'inexistente' => 99]);

it('rechaza fechas fuera del año del plan o con el fin anterior al inicio', function () {
    crearPlanApi($this, cuerpoPlan(['acciones' => [cuerpoAccion(['fecha_inicio' => '2025-12-30', 'fecha_fin' => '2026-01-05'])]]))
        ->assertUnprocessable()->assertJsonPath('code', 'fechas_fuera_del_anio');
    crearPlanApi($this, cuerpoPlan(['acciones' => [cuerpoAccion(['fecha_inicio' => '2026-12-30', 'fecha_fin' => '2027-01-02'])]]))
        ->assertUnprocessable()->assertJsonPath('code', 'fechas_fuera_del_anio');
    crearPlanApi($this, cuerpoPlan(['acciones' => [cuerpoAccion(['fecha_inicio' => '2026-05-10', 'fecha_fin' => '2026-05-09'])]]))
        ->assertUnprocessable()->assertJsonValidationErrors(['acciones.0.fecha_fin']);
});

it('rechaza horas, capacidad o costo fuera de rango', function (string $campo, mixed $valor) {
    crearPlanApi($this, cuerpoPlan(['acciones' => [cuerpoAccion([$campo => $valor])]]))
        ->assertUnprocessable()->assertJsonValidationErrors(["acciones.0.{$campo}"]);
})->with([
    'horas cero' => ['duracion_horas', 0],
    'horas negativas' => ['duracion_horas', -2],
    'capacidad cero' => ['capacidad_maxima', 0],
    'costo negativo' => ['costo', -1],
]);

it('acepta costo cero', function () {
    crearPlanApi($this, cuerpoPlan(['acciones' => [cuerpoAccion(['costo' => 0])]]))->assertCreated()->assertJsonPath('data.costo_total', 0);
});

it('valida los participantes: repetidos, sin vínculo vigente y sobre la capacidad', function () {
    crearPlanApi($this, cuerpoPlan(['acciones' => [cuerpoAccion(['colaborador_ids' => [10, 10]])]]))
        ->assertUnprocessable()->assertJsonPath('code', 'participantes_repetidos');
    crearPlanApi($this, cuerpoPlan(['acciones' => [cuerpoAccion(['colaborador_ids' => [10, 77]])]]))
        ->assertUnprocessable()->assertJsonPath('code', 'colaborador_invalido');
    crearPlanApi($this, cuerpoPlan(['acciones' => [cuerpoAccion(['capacidad_maxima' => 2, 'colaborador_ids' => [10, 11, 12]])]]))
        ->assertUnprocessable()->assertJsonPath('code', 'cupo_excedido');
    expect(PlanModel::query()->count())->toBe(0);
});

it('rechaza un aprobador que no es un administrador', function () {
    crearPlanApi($this, cuerpoPlan(['aprobador_id' => 999]))
        ->assertUnprocessable()->assertJsonPath('code', 'aprobador_invalido');
});

it('exige autenticación y rol de administrador', function () {
    $this->app['auth']->forgetGuards();
    $this->postJson('/api/planes', cuerpoPlan())->assertUnauthorized();

    actuarComoUsuarioDeTenant();
    $this->postJson('/api/planes', cuerpoPlan())->assertForbidden();
});
