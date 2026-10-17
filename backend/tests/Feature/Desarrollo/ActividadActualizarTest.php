<?php

use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\ActividadModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PlanAccionModel;

beforeEach(function () {
    $this->falsos = actividadFalsos();
    $this->admin = actuarComoAdminDeTenant();
    $this->otro = actuarComoAdminDeTenant();
    $this->actualizar = fn (int $id, array $cuerpo) => $this->putJson("/api/actividades/{$id}", $cuerpo);
});

it('reemplaza lugar o enlace y departamento y registra quién modificó', function () {
    [, $accion] = accionEnPlan();
    $a = actividadDe($accion, 'Sala 1', 1, actorId: $this->admin->id);

    ($this->actualizar)($a['id'], ['lugar_enlace' => 'Auditorio principal', 'departamento_id' => 2])->assertOk()
        ->assertJsonPath('data.lugar_enlace', 'Auditorio principal')
        ->assertJsonPath('data.departamento.nombre', 'Talento Humano')
        ->assertJsonPath('data.auditoria.creado_por', $this->admin->name)
        ->assertJsonPath('data.auditoria.modificado_por', $this->otro->name);

    $m = ActividadModel::query()->find($a['id']);
    expect($m->created_by)->toBe($this->admin->id)->and($m->updated_by)->toBe($this->otro->id);
});

it('permite quitar el departamento', function () {
    [, $accion] = accionEnPlan();
    $a = actividadDe($accion, 'Sala 1', 1);

    ($this->actualizar)($a['id'], ['lugar_enlace' => 'Sala 1', 'departamento_id' => null])->assertOk()->assertJsonPath('data.departamento', null);
});

it('ignora el código, la acción y los demás campos', function () {
    [, $accion] = accionEnPlan();
    [, $otraAccion] = accionEnPlan();
    $a = actividadDe($accion);

    ($this->actualizar)($a['id'], ['lugar_enlace' => 'Nuevo', 'codigo' => 'ACT-9999', 'plan_accion_id' => $otraAccion, 'version' => 50, 'nombre' => 'Hackeado'])->assertOk()
        ->assertJsonPath('data.codigo', 'ACT-0001')->assertJsonPath('data.accion_id', $accion)->assertJsonPath('data.nombre', 'Análisis con SQL')->assertJsonPath('data.version', 1);
});

it('aplica la validación de URL según el tipo de la acción', function (string $tipo) {
    [, $accion] = accionEnPlan(['tipo' => $tipo]);
    $a = actividadDe($accion, 'https://meet.ejemplo.edu.ec/inicial');

    ($this->actualizar)($a['id'], ['lugar_enlace' => 'sala 3'])->assertUnprocessable()->assertJsonPath('code', 'lugar_invalido');
    ($this->actualizar)($a['id'], ['lugar_enlace' => 'https://meet.ejemplo.edu.ec/nuevo'])->assertOk();
})->with(['Virtual', 'ELearning']);

it('valida lugar y departamento', function () {
    [, $accion] = accionEnPlan();
    $a = actividadDe($accion);

    ($this->actualizar)($a['id'], ['departamento_id' => 1])->assertUnprocessable()->assertJsonValidationErrors('lugar_enlace');
    ($this->actualizar)($a['id'], ['lugar_enlace' => str_repeat('a', 501)])->assertUnprocessable()->assertJsonValidationErrors('lugar_enlace');
    ($this->actualizar)($a['id'], ['lugar_enlace' => 'Sala', 'departamento_id' => 77])->assertUnprocessable()->assertJsonPath('code', 'departamento_invalido');
    ($this->actualizar)($a['id'], ['lugar_enlace' => 'Sala', 'departamento_id' => 9])->assertUnprocessable()->assertJsonPath('code', 'departamento_invalido');
});

it('conserva un departamento que pasó a inactivo si no se cambia', function () {
    [, $accion] = accionEnPlan();
    $a = actividadDe($accion, 'Sala 1', 2);
    $this->falsos['departamentos']->inactivos = [2 => 'Talento Humano'];
    $this->falsos['departamentos']->activos = [1 => 'Tecnología'];

    ($this->actualizar)($a['id'], ['lugar_enlace' => 'Sala 2', 'departamento_id' => 2])->assertOk()->assertJsonPath('data.departamento.nombre', 'Talento Humano');
});

it('permite editar una actividad cancelada mientras el plan no esté Cerrado', function () {
    [, $accion] = accionEnPlan();
    $a = actividadDe($accion);
    PlanAccionModel::query()->whereKey($accion)->update(['cancelada' => true]);

    ($this->actualizar)($a['id'], ['lugar_enlace' => 'Otra sala'])->assertOk()->assertJsonPath('data.estado', 'Cancelada');
});

it('rechaza editar con el plan Cerrado', function () {
    [$plan, $accion] = accionEnPlan();
    $a = actividadDe($accion, 'Sala original');
    forzarEstadoPlan($plan, 'Cerrado');

    ($this->actualizar)($a['id'], ['lugar_enlace' => 'Otra sala'])->assertUnprocessable()->assertJsonPath('code', 'plan_cerrado');
    expect(ActividadModel::query()->find($a['id'])->lugar_enlace)->toBe('Sala original');
});

it('devuelve 404 si no existe o es de otro tenant y exige administrador', function () {
    [, $accion] = accionEnPlan();
    $a = actividadDe($accion);

    ($this->actualizar)(9999, ['lugar_enlace' => 'x'])->assertNotFound();
    actuarComoAdminDeTenant(2);
    ($this->actualizar)($a['id'], ['lugar_enlace' => 'x'])->assertNotFound();
    actuarComoUsuarioDeTenant();
    ($this->actualizar)($a['id'], ['lugar_enlace' => 'x'])->assertForbidden();
    app('auth')->forgetGuards();
    ($this->actualizar)($a['id'], ['lugar_enlace' => 'x'])->assertUnauthorized();
});

it('no existe ninguna ruta para eliminar una actividad', function () {
    [, $accion] = accionEnPlan();
    $a = actividadDe($accion);

    $this->deleteJson("/api/actividades/{$a['id']}")->assertStatus(405);
    expect(ActividadModel::query()->count())->toBe(1);
});
