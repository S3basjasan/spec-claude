<?php

use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PlanAccionModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PlanAccionParticipanteModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PlanHistorialModel;

beforeEach(function () {
    $this->falsos = planFalsos();
    $this->admin = actuarComoAdminDeTenant();
    $this->falsos['aprobadores']->administradores[$this->admin->id] = $this->admin->name;
    $this->plan = function (string $estado = 'Aprobado', array $accion = []) {
        $plan = planGuardado(['nombre' => 'Plan '.uniqid(), 'aprobador_id' => $this->admin->id, 'acciones' => [cuerpoAccion($accion + ['capacidad_maxima' => 3])]], actorId: $this->admin->id);
        forzarEstadoPlan($plan['id'], $estado);

        return [$plan['id'], $plan['acciones'][0]['id']];
    };
    $this->cambiar = fn (int $accion, array $ids) => $this->putJson("/api/plan-acciones/{$accion}/participantes", ['colaborador_ids' => $ids]);
});

it('permite cambiar participantes en Borrador, Aprobado y Ejecutando', function (string $estado) {
    [, $accion] = ($this->plan)($estado);

    ($this->cambiar)($accion, [10, 12, 13])->assertOk()
        ->assertJsonCount(3, 'data.accion.participantes')
        ->assertJsonPath('data.accion.participantes.2.nombre', 'Colaborador 13');
    expect(PlanAccionParticipanteModel::query()->where('plan_accion_id', $accion)->pluck('colaborador_id')->map(fn ($i) => (int) $i)->sort()->values()->all())->toBe([10, 12, 13]);
})->with(['Borrador', 'Aprobado', 'Ejecutando']);

it('rechaza cambiar participantes en Revisión y Cerrado', function (string $estado) {
    [, $accion] = ($this->plan)($estado);

    ($this->cambiar)($accion, [12])->assertUnprocessable()->assertJsonPath('code', 'plan_no_editable');
    expect(PlanAccionParticipanteModel::query()->count())->toBe(2);
})->with(['Revision', 'Cerrado']);

it('valida repetidos, cupo y colaboradores inexistentes o sin vínculo', function () {
    [, $accion] = ($this->plan)();

    ($this->cambiar)($accion, [10, 10])->assertUnprocessable()->assertJsonPath('code', 'participantes_repetidos');
    ($this->cambiar)($accion, [10, 11, 12, 13])->assertUnprocessable()->assertJsonPath('code', 'cupo_excedido');
    ($this->cambiar)($accion, [10, 77])->assertUnprocessable()->assertJsonPath('code', 'colaborador_invalido');
    $this->putJson("/api/plan-acciones/{$accion}/participantes", [])->assertUnprocessable();
    expect(PlanAccionParticipanteModel::query()->count())->toBe(2);
});

it('conserva a quien perdió el vínculo vigente, sigue ocupando cupo y se puede quitar manualmente', function () {
    [$plan, $accion] = ($this->plan)('Aprobado', ['capacidad_maxima' => 2]);
    unset($this->falsos['vigentes']->vigentes[11]);

    // Sigue inscrito y ocupa cupo: no se puede agregar a otro hasta quitarlo.
    ($this->cambiar)($accion, [10, 11])->assertOk()->assertJsonPath('data.accion.participantes.1.vinculo_vigente', false);
    ($this->cambiar)($accion, [10, 11, 12])->assertUnprocessable()->assertJsonPath('code', 'cupo_excedido');
    ($this->cambiar)($accion, [10])->assertOk();

    $quitado = PlanHistorialModel::query()->where('plan_id', $plan)->where('tipo', 'Participante')->sole();
    expect($quitado->valor_anterior)->toBe('Colaborador 11')->and($quitado->valor_nuevo)->toBeNull();
    // Quien perdió el vínculo no se puede volver a agregar.
    ($this->cambiar)($accion, [10, 11])->assertUnprocessable()->assertJsonPath('code', 'colaborador_invalido');
});

it('registra en el historial cada participante agregado o quitado solo desde la aprobación', function () {
    [$borrador, $enBorrador] = ($this->plan)('Borrador');
    ($this->cambiar)($enBorrador, [12])->assertOk();
    expect(PlanHistorialModel::query()->where('plan_id', $borrador)->count())->toBe(0);

    [$plan, $accion] = ($this->plan)('Ejecutando');
    ($this->cambiar)($accion, [10, 12, 13])->assertOk();

    $renglones = PlanHistorialModel::query()->where('plan_id', $plan)->orderBy('id')->get();
    expect($renglones)->toHaveCount(3)
        ->and($renglones->pluck('tipo')->unique()->all())->toBe(['Participante'])
        ->and($renglones[0]->valor_anterior)->toBeNull()->and($renglones[0]->valor_nuevo)->toBe('Colaborador 12')
        ->and($renglones[1]->valor_nuevo)->toBe('Colaborador 13')
        ->and($renglones[2]->valor_anterior)->toBe('Colaborador 11')->and($renglones[2]->valor_nuevo)->toBeNull()
        ->and((int) $renglones[0]->plan_accion_id)->toBe($accion)
        ->and((int) $renglones[0]->user_id)->toBe($this->admin->id);
});

it('devuelve avisos de cruce de fechas sin impedir el cambio', function () {
    [, $accion] = ($this->plan)();
    $otro = planGuardado(['nombre' => 'Otro plan', 'acciones' => [cuerpoAccion(['fecha_inicio' => '2026-03-10', 'fecha_fin' => '2026-03-20', 'colaborador_ids' => [14]])]]);

    $respuesta = ($this->cambiar)($accion, [10, 14])->assertOk();

    expect($respuesta->json('data.avisos_cruce'))->toHaveCount(1)
        ->and($respuesta->json('data.avisos_cruce.0.colaborador_id'))->toBe(14)
        ->and($respuesta->json('data.avisos_cruce.0.con_plan_id'))->toBe($otro['id']);
});

it('rechaza cambiar participantes de una acción cancelada', function () {
    [, $accion] = ($this->plan)();
    PlanAccionModel::query()->whereKey($accion)->update(['cancelada' => true, 'motivo_cancelacion' => 'x']);

    ($this->cambiar)($accion, [12])->assertUnprocessable()->assertJsonPath('code', 'accion_cancelada');
});

it('responde 404 para acciones inexistentes o de otro tenant y 403 a un colaborador', function () {
    [, $accion] = ($this->plan)();

    ($this->cambiar)(9999, [10])->assertNotFound();
    actuarComoAdminDeTenant(2);
    ($this->cambiar)($accion, [10])->assertNotFound();
    actuarComoUsuarioDeTenant();
    ($this->cambiar)($accion, [10])->assertForbidden();
});
