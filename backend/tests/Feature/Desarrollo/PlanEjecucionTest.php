<?php

use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PlanAccionModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PlanHistorialModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PlanModel;

beforeEach(function () {
    $this->falsos = planFalsos(['hoy' => '2026-06-15']);
    $this->admin = actuarComoAdminDeTenant();
    $this->falsos['aprobadores']->administradores[$this->admin->id] = $this->admin->name;
    $this->plan = function (string $estado, array $acciones) {
        $plan = planGuardado(['nombre' => 'Plan '.uniqid(), 'aprobador_id' => $this->admin->id, 'acciones' => $acciones], actorId: $this->admin->id);
        forzarEstadoPlan($plan['id'], $estado);

        return $plan;
    };
    $this->estado = fn (int $id, string $estado) => $this->patchJson("/api/planes/{$id}/estado", ['estado' => $estado]);
});

it('inicia la ejecución de un plan aprobado y lo registra', function () {
    $plan = ($this->plan)('Aprobado', [cuerpoAccion()]);

    ($this->estado)($plan['id'], 'Ejecutando')->assertOk()->assertJsonPath('data.estado', 'Ejecutando')->assertJsonPath('data.puede.cerrar', true);

    $renglon = PlanHistorialModel::query()->where('plan_id', $plan['id'])->sole();
    expect($renglon->valor_anterior)->toBe('Aprobado')->and($renglon->valor_nuevo)->toBe('Ejecutando');
});

it('no cierra un plan con acciones programadas o en curso', function (string $inicio, string $fin) {
    $plan = ($this->plan)('Ejecutando', [cuerpoAccion(['fecha_inicio' => '2026-01-05', 'fecha_fin' => '2026-01-10']), cuerpoAccion(['nombre' => 'Otra', 'fecha_inicio' => $inicio, 'fecha_fin' => $fin])]);

    ($this->estado)($plan['id'], 'Cerrado')->assertUnprocessable()->assertJsonPath('code', 'acciones_sin_finalizar');
    expect(PlanModel::query()->find($plan['id'])->estado)->toBe('Ejecutando');
})->with([
    'en curso' => ['2026-06-10', '2026-06-20'],
    'programada' => ['2026-09-01', '2026-09-10'],
    'termina hoy' => ['2026-06-01', '2026-06-15'],
]);

it('cierra un plan cuando todas las acciones están finalizadas o canceladas, incluso si todas están canceladas', function () {
    $plan = ($this->plan)('Ejecutando', [cuerpoAccion(['fecha_inicio' => '2026-01-05', 'fecha_fin' => '2026-01-10']), cuerpoAccion(['nombre' => 'Cancelada', 'fecha_inicio' => '2026-09-01', 'fecha_fin' => '2026-09-10'])]);
    PlanAccionModel::query()->whereKey($plan['acciones'][1]['id'])->update(['cancelada' => true, 'motivo_cancelacion' => 'Sin cupo']);

    ($this->estado)($plan['id'], 'Cerrado')->assertOk()->assertJsonPath('data.estado', 'Cerrado');

    $soloCanceladas = ($this->plan)('Ejecutando', [cuerpoAccion(['nombre' => 'Única', 'fecha_inicio' => '2026-09-01', 'fecha_fin' => '2026-09-10'])] + []);
    PlanAccionModel::query()->whereKey($soloCanceladas['acciones'][0]['id'])->update(['cancelada' => true, 'motivo_cancelacion' => 'x']);
    ($this->estado)($soloCanceladas['id'], 'Cerrado')->assertOk();
});

it('un plan cerrado no admite cambios ni retroceso', function () {
    $plan = ($this->plan)('Cerrado', [cuerpoAccion()]);
    $accion = $plan['acciones'][0]['id'];

    $this->putJson('/api/planes/'.$plan['id'], cuerpoPlan())->assertUnprocessable()->assertJsonPath('code', 'plan_no_editable');
    ($this->estado)($plan['id'], 'Ejecutando')->assertUnprocessable();
    ($this->estado)($plan['id'], 'Borrador')->assertUnprocessable();
    $this->putJson("/api/plan-acciones/{$accion}/participantes", ['colaborador_ids' => [12]])->assertUnprocessable()->assertJsonPath('code', 'plan_no_editable');
    $this->patchJson("/api/plan-acciones/{$accion}/cancelar", ['motivo' => 'x'])->assertUnprocessable()->assertJsonPath('code', 'plan_no_editable');
});

it('en Aprobado y Ejecutando no se editan los datos de la acción', function (string $estado) {
    $plan = ($this->plan)($estado, [cuerpoAccion()]);

    $this->putJson('/api/planes/'.$plan['id'], cuerpoPlan(['nombre' => 'Cambio', 'acciones' => [cuerpoAccion(['id' => $plan['acciones'][0]['id'], 'costo' => 1])]]))
        ->assertUnprocessable()->assertJsonPath('code', 'plan_no_editable');
    expect(PlanModel::query()->find($plan['id'])->nombre)->toBe($plan['nombre'])
        ->and((float) PlanAccionModel::query()->first()->costo)->toBe(800.0);
})->with(['Aprobado', 'Ejecutando']);

it('no existe ninguna ruta para eliminar planes ni acciones', function () {
    $plan = ($this->plan)('Borrador', [cuerpoAccion()]);
    $accion = $plan['acciones'][0]['id'];

    expect($this->deleteJson('/api/planes/'.$plan['id'])->status())->toBeIn([404, 405]);
    expect($this->deleteJson("/api/plan-acciones/{$accion}")->status())->toBeIn([404, 405]);
    expect(PlanModel::query()->count())->toBe(1)->and(PlanAccionModel::query()->count())->toBe(1);
});
