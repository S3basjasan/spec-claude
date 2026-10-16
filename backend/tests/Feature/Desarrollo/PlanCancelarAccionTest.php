<?php

use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PlanHistorialModel;

beforeEach(function () {
    $this->falsos = planFalsos();
    $this->admin = actuarComoAdminDeTenant();
    $this->falsos['aprobadores']->administradores[$this->admin->id] = $this->admin->name;
    $this->plan = function (string $estado = 'Aprobado') {
        $plan = planGuardado(['aprobador_id' => $this->admin->id, 'acciones' => [cuerpoAccion(['costo' => 100, 'duracion_horas' => 10]), cuerpoAccion(['nombre' => 'B', 'costo' => 50, 'duracion_horas' => 5])]], actorId: $this->admin->id);
        forzarEstadoPlan($plan['id'], $estado);

        return $plan;
    };
    $this->cancelar = fn (int $accion, ?string $motivo) => $this->patchJson("/api/plan-acciones/{$accion}/cancelar", $motivo === null ? [] : ['motivo' => $motivo]);
});

it('cancela una acción de un plan Aprobado o Ejecutando con motivo y la deja de contar en los totales', function (string $estado) {
    $plan = ($this->plan)($estado);
    $accion = $plan['acciones'][0]['id'];

    ($this->cancelar)($accion, 'Sin cupo')->assertOk()
        ->assertJsonPath('data.cancelada', true)
        ->assertJsonPath('data.estado_accion', 'Cancelada')
        ->assertJsonPath('data.motivo_cancelacion', 'Sin cupo');

    $this->getJson('/api/planes/'.$plan['id'])->assertJsonPath('data.cantidad_acciones', 1)->assertJsonPath('data.costo_total', 50)->assertJsonPath('data.horas_totales', 5);
    $renglon = PlanHistorialModel::query()->where('plan_id', $plan['id'])->sole();
    expect($renglon->tipo)->toBe('Cancelacion')->and((int) $renglon->plan_accion_id)->toBe($accion)->and($renglon->motivo)->toBe('Sin cupo')->and((int) $renglon->user_id)->toBe($this->admin->id);
})->with(['Aprobado', 'Ejecutando']);

it('exige un motivo', function (?string $motivo) {
    $plan = ($this->plan)();

    ($this->cancelar)($plan['acciones'][0]['id'], $motivo)->assertUnprocessable()->assertJsonPath('code', 'motivo_requerido');
    expect(PlanHistorialModel::query()->count())->toBe(0);
})->with([null, '', '   ']);

it('rechaza cancelar dos veces', function () {
    $plan = ($this->plan)();
    $accion = $plan['acciones'][0]['id'];
    ($this->cancelar)($accion, 'Uno')->assertOk();

    ($this->cancelar)($accion, 'Dos')->assertUnprocessable()->assertJsonPath('code', 'accion_ya_cancelada');
    expect(PlanHistorialModel::query()->count())->toBe(1);
});

it('solo permite cancelar en Aprobado y Ejecutando', function (string $estado) {
    $plan = ($this->plan)($estado);

    ($this->cancelar)($plan['acciones'][0]['id'], 'x')->assertUnprocessable()->assertJsonPath('code', 'plan_no_editable');
})->with(['Borrador', 'Revision', 'Cerrado']);

it('responde 404 y 403 según corresponda', function () {
    $plan = ($this->plan)();
    $accion = $plan['acciones'][0]['id'];

    ($this->cancelar)(9999, 'x')->assertNotFound();
    actuarComoAdminDeTenant(2);
    ($this->cancelar)($accion, 'x')->assertNotFound();
    actuarComoUsuarioDeTenant();
    ($this->cancelar)($accion, 'x')->assertForbidden();
});
