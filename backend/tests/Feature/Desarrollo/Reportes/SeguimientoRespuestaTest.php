<?php

use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\SeguimientoImpactoModel;
use Laravel\Sanctum\Sanctum;

/**
 * Acción 2026-03-02 a 2026-03-13. Hito 30 vence el 2026-04-12 (ventana hasta el 2026-04-27); hito 60 el 2026-05-12
 * (hasta el 2026-05-27); hito 90 el 2026-06-11 (hasta el 2026-06-26).
 */
beforeEach(function () {
    actuarComoAdminDeTenant();
    [$this->usuario, $this->colaborador] = colaboradorConCuentaDePlan();
    $this->falsos = actividadFalsos(['hoy' => '2026-04-12', 'vigentes' => [$this->colaborador => 'Diego Paredes', 99 => 'Otra persona']]);
    Sanctum::actingAs($this->usuario, ['user']);
    $this->actividad = fn (string $asistencia = 'Presente') => actividadConAsistencia([$this->colaborador => $asistencia, 99 => 'Presente'])['actividad'];
    $this->responder = fn (int $id, array $cambios = []) => $this->postJson('/api/reportes/seguimientos', array_replace(
        ['actividad_id' => $id, 'hito' => 30, 'aplicacion' => 4, 'apoyo' => 3, 'comentario' => 'Aplico lo aprendido'],
        $cambios,
    ));
});

it('registra el hito el día del vencimiento con su fecha', function () {
    $id = ($this->actividad)();

    ($this->responder)($id)->assertCreated()
        ->assertJsonPath('data.hito', 30)->assertJsonPath('data.aplicacion', 4)->assertJsonPath('data.apoyo', 3)->assertJsonPath('data.comentario', 'Aplico lo aprendido');

    $m = SeguimientoImpactoModel::query()->firstOrFail();
    expect($m->colaborador_id)->toBe($this->colaborador)->and($m->created_at)->not->toBeNull();
});

it('rechaza un día antes del vencimiento', function () {
    $this->falsos['reloj']->fecha = '2026-04-11';
    $id = ($this->actividad)();

    ($this->responder)($id)->assertUnprocessable()->assertJsonPath('code', 'hito_no_vencido');
});

it('acepta hasta 15 días después del vencimiento y rechaza al día 16', function () {
    $id = ($this->actividad)();

    $this->falsos['reloj']->fecha = '2026-04-28';
    ($this->responder)($id)->assertUnprocessable()->assertJsonPath('code', 'ventana_cerrada');

    $this->falsos['reloj']->fecha = '2026-04-27';
    ($this->responder)($id)->assertCreated();
});

it('un hito vencido sin respuesta queda sin respuesta', function () {
    $this->falsos['reloj']->fecha = '2026-05-01';
    $id = ($this->actividad)();

    ($this->responder)($id)->assertUnprocessable()->assertJsonPath('code', 'ventana_cerrada');
    expect(SeguimientoImpactoModel::query()->count())->toBe(0);
});

it('acepta los hitos 60 y 90 en su ventana y no repite un mismo hito', function () {
    $id = ($this->actividad)();

    $this->falsos['reloj']->fecha = '2026-05-12';
    ($this->responder)($id, ['hito' => 60])->assertCreated();
    $this->falsos['reloj']->fecha = '2026-06-11';
    ($this->responder)($id, ['hito' => 90])->assertCreated();
    ($this->responder)($id, ['hito' => 90, 'aplicacion' => 1])->assertUnprocessable()->assertJsonPath('code', 'ya_respondida');

    expect(SeguimientoImpactoModel::query()->count())->toBe(2);
});

it('otro hito de la misma actividad se acepta aunque ya respondiera uno', function () {
    $id = ($this->actividad)();
    ($this->responder)($id)->assertCreated();

    $this->falsos['reloj']->fecha = '2026-05-12';
    ($this->responder)($id, ['hito' => 60])->assertCreated();
});

it('rechaza hitos que no son 30, 60 ni 90', function (int $hito) {
    $id = ($this->actividad)();

    ($this->responder)($id, ['hito' => $hito])->assertUnprocessable()->assertJsonPath('code', 'hito_invalido');
})->with([45, 0, 91]);

it('valida las dos calificaciones', function (string $campo, mixed $valor) {
    $id = ($this->actividad)();

    ($this->responder)($id, [$campo => $valor])->assertUnprocessable()->assertJsonValidationErrors($campo);
    expect(SeguimientoImpactoModel::query()->count())->toBe(0);
})->with([
    ['aplicacion', 0], ['aplicacion', 6], ['aplicacion', 2.5], ['aplicacion', null],
    ['apoyo', 0], ['apoyo', 6], ['apoyo', 'x'], ['apoyo', null],
]);

it('el comentario es opcional y limitado a 1 000 caracteres', function () {
    $id = ($this->actividad)();

    ($this->responder)($id, ['comentario' => str_repeat('a', 1001)])->assertUnprocessable()->assertJsonValidationErrors('comentario');
    ($this->responder)($id, ['comentario' => null])->assertCreated()->assertJsonPath('data.comentario', null);
});

it('rechaza si la actividad no está finalizada o el colaborador no estuvo presente', function () {
    $this->falsos['reloj']->fecha = '2026-03-05';
    $enCurso = ($this->actividad)();
    ($this->responder)($enCurso)->assertUnprocessable()->assertJsonPath('code', 'actividad_no_finalizada');

    $this->falsos['reloj']->fecha = '2026-04-12';
    [, $accion] = accionEnPlan(['colaborador_ids' => [$this->colaborador, 99]]);
    $ausente = actividadDe($accion);
    registroDeAsistencia($ausente['id'], $this->colaborador, 'Ausente');
    ($this->responder)($ausente['id'])->assertUnprocessable()->assertJsonPath('code', 'sin_asistencia');
});

it('responde 404 con una actividad inexistente', function () {
    ($this->responder)(9999)->assertNotFound()->assertJsonPath('code', 'actividad_no_encontrada');
});

it('no existen PUT ni DELETE sobre los seguimientos', function () {
    $id = ($this->actividad)();
    ($this->responder)($id)->assertCreated();

    $this->putJson('/api/reportes/seguimientos', ['actividad_id' => $id, 'hito' => 30, 'aplicacion' => 1, 'apoyo' => 1])->assertStatus(405);
    $this->deleteJson('/api/reportes/seguimientos/1')->assertStatus(404);
    expect(SeguimientoImpactoModel::query()->first()->aplicacion)->toBe(4);
});
