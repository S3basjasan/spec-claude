<?php

use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PlanHistorialModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PlanModel;
use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->falsos = planFalsos();
    $this->creador = actuarComoAdminDeTenant();
    $this->aprobador = UserModel::factory()->admin()->create(['tenant_id' => 1]);
    $this->falsos['aprobadores']->administradores[$this->creador->id] = $this->creador->name;
    $this->falsos['aprobadores']->administradores[$this->aprobador->id] = $this->aprobador->name;
    $this->plan = fn (array $cambios = []) => planGuardado(array_replace(['aprobador_id' => $this->aprobador->id], $cambios), actorId: $this->creador->id);
    $this->como = fn (UserModel $usuario) => Sanctum::actingAs($usuario, ['admin']);
    $this->estado = fn (int $id, string $estado, ?string $motivo = null) => $this->patchJson("/api/planes/{$id}/estado", array_filter(['estado' => $estado, 'motivo' => $motivo]));
});

it('rechaza enviar a revisión un plan incompleto y lista lo que falta', function (array $cambios, array $esperado) {
    $plan = ($this->plan)($cambios);

    $respuesta = ($this->estado)($plan['id'], 'Revision')->assertUnprocessable()->assertJsonPath('code', 'plan_incompleto');

    expect(collect($respuesta->json('faltantes'))->pluck('campo')->all())->toBe($esperado);
    expect(PlanModel::query()->find($plan['id'])->estado)->toBe('Borrador');
})->with([
    'sin acciones' => [['acciones' => []], ['acciones']],
    'sin aprobador' => [['aprobador_id' => null], ['aprobador']],
    'acción sin datos' => [['acciones' => [['nombre' => 'Pendiente', 'competencia_ids' => [1]]]], ['tipo', 'fechas', 'duracion_horas', 'formador', 'capacidad_maxima', 'costo', 'participantes']],
    'acción sin participantes' => [['acciones' => [['colaborador_ids' => []] + cuerpoAccion()]], ['participantes']],
]);

it('envía a revisión un plan completo: queda de solo lectura y se registra en el historial', function () {
    $plan = ($this->plan)();

    ($this->estado)($plan['id'], 'Revision')->assertOk()
        ->assertJsonPath('data.estado', 'Revision')
        ->assertJsonPath('data.puede.editar', false)
        ->assertJsonPath('data.puede.aprobar', false);

    $this->putJson('/api/planes/'.$plan['id'], cuerpoPlan())->assertUnprocessable()->assertJsonPath('code', 'plan_no_editable');
    $renglon = PlanHistorialModel::query()->where('plan_id', $plan['id'])->sole();
    expect($renglon->tipo)->toBe('Estado')
        ->and($renglon->valor_anterior)->toBe('Borrador')
        ->and($renglon->valor_nuevo)->toBe('Revision')
        ->and((int) $renglon->user_id)->toBe($this->creador->id);
});

it('solo el aprobador designado puede aprobar o devolver', function (string $destino) {
    $plan = ($this->plan)();
    ($this->estado)($plan['id'], 'Revision')->assertOk();

    ($this->estado)($plan['id'], $destino, 'Falta detalle')->assertForbidden()->assertJsonPath('code', 'usuario_no_es_aprobador');

    expect(PlanModel::query()->find($plan['id'])->estado)->toBe('Revision');
    ($this->como)($this->aprobador);
    $this->getJson('/api/planes/'.$plan['id'])->assertJsonPath('data.puede.aprobar', true)->assertJsonPath('data.puede.devolver', true);
})->with(['Aprobado', 'Borrador']);

it('exige un motivo al devolver a Borrador y lo registra', function () {
    $plan = ($this->plan)();
    ($this->estado)($plan['id'], 'Revision')->assertOk();
    ($this->como)($this->aprobador);

    ($this->estado)($plan['id'], 'Borrador')->assertUnprocessable()->assertJsonPath('code', 'motivo_requerido');
    ($this->estado)($plan['id'], 'Borrador', '   ')->assertUnprocessable()->assertJsonPath('code', 'motivo_requerido');
    ($this->estado)($plan['id'], 'Borrador', 'Falta detallar costos')->assertOk()->assertJsonPath('data.estado', 'Borrador')->assertJsonPath('data.puede.editar', true);

    $renglon = PlanHistorialModel::query()->where('plan_id', $plan['id'])->orderByDesc('id')->first();
    expect($renglon->valor_anterior)->toBe('Revision')->and($renglon->valor_nuevo)->toBe('Borrador')->and($renglon->motivo)->toBe('Falta detallar costos')
        ->and((int) $renglon->user_id)->toBe($this->aprobador->id);
});

it('aprueba un plan en Revisión y registra la fecha de aprobación', function () {
    $plan = ($this->plan)();
    ($this->estado)($plan['id'], 'Revision')->assertOk();
    ($this->como)($this->aprobador);

    $respuesta = ($this->estado)($plan['id'], 'Aprobado')->assertOk()->assertJsonPath('data.estado', 'Aprobado');

    expect($respuesta->json('data.fecha_aprobacion'))->not->toBeNull();
    expect(PlanModel::query()->find($plan['id'])->fecha_aprobacion)->not->toBeNull();
    expect(PlanHistorialModel::query()->where('plan_id', $plan['id'])->count())->toBe(2);
});

it('puede reenviarse y aprobarse tras ser devuelto', function () {
    $plan = ($this->plan)();
    ($this->estado)($plan['id'], 'Revision')->assertOk();
    ($this->como)($this->aprobador);
    ($this->estado)($plan['id'], 'Borrador', 'Corregir')->assertOk();
    ($this->como)($this->creador);

    ($this->estado)($plan['id'], 'Revision')->assertOk();
    ($this->como)($this->aprobador);
    ($this->estado)($plan['id'], 'Aprobado')->assertOk()->assertJsonPath('data.estado', 'Aprobado');
});

it('rechaza las transiciones que no existen y no permite retroceder desde Aprobado', function (string $origen, string $destino) {
    $plan = ($this->plan)();
    forzarEstadoPlan($plan['id'], $origen);
    ($this->como)($this->aprobador);

    ($this->estado)($plan['id'], $destino, 'x')->assertUnprocessable()->assertJsonPath('code', 'transicion_invalida');
    expect(PlanModel::query()->find($plan['id'])->estado)->toBe($origen);
})->with([
    ['Borrador', 'Aprobado'],
    ['Borrador', 'Ejecutando'],
    ['Borrador', 'Cerrado'],
    ['Borrador', 'Borrador'],
    ['Revision', 'Ejecutando'],
    ['Aprobado', 'Borrador'],
    ['Aprobado', 'Revision'],
    ['Aprobado', 'Cerrado'],
    ['Ejecutando', 'Aprobado'],
    ['Cerrado', 'Ejecutando'],
    ['Cerrado', 'Borrador'],
]);

it('responde 404, 422 y 403 según corresponda', function () {
    ($this->estado)(9999, 'Revision')->assertNotFound();
    $this->patchJson('/api/planes/1/estado', ['estado' => 'Otro'])->assertUnprocessable();
    $this->patchJson('/api/planes/1/estado', [])->assertUnprocessable();

    $plan = ($this->plan)();
    actuarComoUsuarioDeTenant();
    ($this->estado)($plan['id'], 'Revision')->assertForbidden();
    actuarComoAdminDeTenant(2);
    ($this->estado)($plan['id'], 'Revision')->assertNotFound();
});
