<?php

use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\ActividadModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PlanAccionModel;

beforeEach(function () {
    $this->falsos = actividadFalsos();
    $this->admin = actuarComoAdminDeTenant();
    $this->crear = fn (array $cuerpo) => $this->postJson('/api/actividades', $cuerpo);
});

it('crea la actividad con código ACT-0001, datos heredados y auditoría', function () {
    [, $accion] = accionEnPlan();

    $r = ($this->crear)(['plan_accion_id' => $accion, 'lugar_enlace' => 'Sala de capacitación, piso 3', 'departamento_id' => 2])
        ->assertCreated()
        ->assertJsonPath('data.codigo', 'ACT-0001')
        ->assertJsonPath('data.accion_id', $accion)
        ->assertJsonPath('data.nombre', 'Análisis con SQL')
        ->assertJsonPath('data.tipo', 'Presencial')
        ->assertJsonPath('data.estado', 'EnCurso')
        ->assertJsonPath('data.lugar_enlace', 'Sala de capacitación, piso 3')
        ->assertJsonPath('data.departamento.nombre', 'Talento Humano')
        ->assertJsonPath('data.capacidad', 20)
        ->assertJsonPath('data.inscritos', 2)
        ->assertJsonPath('data.version', 1)
        ->assertJsonPath('data.asistencia_pendiente', false)
        ->assertJsonPath('data.plan.estado', 'Ejecutando')
        ->assertJsonCount(2, 'data.participantes')
        ->assertJsonPath('data.participantes.0.asistencia', 'Pendiente');

    $modelo = ActividadModel::query()->firstOrFail();
    expect($modelo->created_by)->toBe($this->admin->id)->and($modelo->updated_by)->toBe($this->admin->id)
        ->and($modelo->tenant_id)->toBe(1)
        ->and($r->headers->get('Location'))->toContain('/api/actividades/'.$modelo->id);
});

it('asigna códigos secuenciales por tenant', function () {
    [, $a1] = accionEnPlan();
    [, $a2] = accionEnPlan();

    ($this->crear)(['plan_accion_id' => $a1, 'lugar_enlace' => 'Sala 1'])->assertCreated()->assertJsonPath('data.codigo', 'ACT-0001');
    ($this->crear)(['plan_accion_id' => $a2, 'lugar_enlace' => 'Sala 2'])->assertCreated()->assertJsonPath('data.codigo', 'ACT-0002');
});

it('acepta el departamento vacío', function () {
    [, $accion] = accionEnPlan();

    ($this->crear)(['plan_accion_id' => $accion, 'lugar_enlace' => 'Sala 1'])->assertCreated()->assertJsonPath('data.departamento', null);
});

it('exige el lugar o enlace y la acción', function () {
    [, $accion] = accionEnPlan();

    ($this->crear)(['plan_accion_id' => $accion])->assertUnprocessable()->assertJsonValidationErrors('lugar_enlace');
    ($this->crear)(['plan_accion_id' => $accion, 'lugar_enlace' => str_repeat('a', 501)])->assertUnprocessable()->assertJsonValidationErrors('lugar_enlace');
    ($this->crear)(['lugar_enlace' => 'Sala 1'])->assertUnprocessable()->assertJsonValidationErrors('plan_accion_id');
    expect(ActividadModel::query()->count())->toBe(0);
});

it('exige una URL válida para acciones Virtual y E-learning', function (string $tipo) {
    [, $accion] = accionEnPlan(['tipo' => $tipo]);

    ($this->crear)(['plan_accion_id' => $accion, 'lugar_enlace' => 'sala 3'])->assertUnprocessable()
        ->assertJsonPath('code', 'lugar_invalido')->assertJsonValidationErrors('lugar_enlace');
    ($this->crear)(['plan_accion_id' => $accion, 'lugar_enlace' => 'https://meet.ejemplo.edu.ec/sala'])->assertCreated();
})->with(['Virtual', 'ELearning']);

it('acepta texto libre en OJT, Codesarrollo y Mentoring', function (string $tipo) {
    [, $accion] = accionEnPlan(['tipo' => $tipo]);

    ($this->crear)(['plan_accion_id' => $accion, 'lugar_enlace' => 'Puesto de trabajo, Finanzas'])->assertCreated();
})->with(['OJT', 'Codesarrollo', 'Mentoring']);

it('rechaza un departamento inexistente o inactivo', function (int $departamento) {
    [, $accion] = accionEnPlan();

    ($this->crear)(['plan_accion_id' => $accion, 'lugar_enlace' => 'Sala 1', 'departamento_id' => $departamento])
        ->assertUnprocessable()->assertJsonPath('code', 'departamento_invalido');
    expect(ActividadModel::query()->count())->toBe(0);
})->with([77, 9]);

it('rechaza acciones que no son elegibles', function (string $caso) {
    [$plan, $accion] = accionEnPlan(estadoPlan: $caso === 'plan' ? 'Aprobado' : 'Ejecutando');
    if ($caso === 'cancelada') {
        PlanAccionModel::query()->whereKey($accion)->update(['cancelada' => true]);
    }
    if ($caso === 'duplicada') {
        ($this->crear)(['plan_accion_id' => $accion, 'lugar_enlace' => 'Sala 1'])->assertCreated();
    }

    ($this->crear)(['plan_accion_id' => $accion, 'lugar_enlace' => 'Sala 2'])->assertUnprocessable()->assertJsonPath('code', 'accion_no_elegible');
    expect(ActividadModel::query()->count())->toBe($caso === 'duplicada' ? 1 : 0);
})->with(['plan', 'cancelada', 'duplicada']);

it('rechaza un plan Cerrado y devuelve 404 si la acción no existe', function () {
    [, $accion] = accionEnPlan(estadoPlan: 'Cerrado');

    ($this->crear)(['plan_accion_id' => $accion, 'lugar_enlace' => 'Sala 1'])->assertUnprocessable()->assertJsonPath('code', 'accion_no_elegible');
    ($this->crear)(['plan_accion_id' => 9999, 'lugar_enlace' => 'Sala 1'])->assertNotFound()->assertJsonPath('code', 'accion_no_encontrada');
});

it('no permite crear sobre una acción de otro tenant', function () {
    [, $accion] = accionEnPlan();
    actuarComoAdminDeTenant(2);

    ($this->crear)(['plan_accion_id' => $accion, 'lugar_enlace' => 'Sala 1'])->assertNotFound();
});

it('exige sesión y capacidad de administrador', function () {
    [, $accion] = accionEnPlan();
    $cuerpo = ['plan_accion_id' => $accion, 'lugar_enlace' => 'Sala 1'];

    app('auth')->forgetGuards();
    $this->postJson('/api/actividades', $cuerpo)->assertUnauthorized();

    actuarComoUsuarioDeTenant();
    $this->postJson('/api/actividades', $cuerpo)->assertForbidden();
    expect(ActividadModel::query()->count())->toBe(0);
});
