<?php

use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\EncuestaSatisfaccionModel;
use Laravel\Sanctum\Sanctum;

/** Acción 2026-03-02 a 2026-03-13: la ventana de la encuesta llega hasta el 2026-04-12 (30 días corridos después del fin). */
beforeEach(function () {
    actuarComoAdminDeTenant();
    [$this->usuario, $this->colaborador] = colaboradorConCuentaDePlan();
    $this->falsos = actividadFalsos(['hoy' => '2026-03-20', 'vigentes' => [$this->colaborador => 'Diego Paredes', 99 => 'Otra persona']]);
    Sanctum::actingAs($this->usuario, ['user']);
    $this->actividad = fn (string $asistencia = 'Presente') => actividadConAsistencia([$this->colaborador => $asistencia, 99 => 'Presente'])['actividad'];
    $this->responder = fn (array $cuerpo) => $this->postJson('/api/reportes/encuestas', $cuerpo);
});

it('registra la encuesta con calificación y comentario, con su fecha', function () {
    $id = ($this->actividad)();

    ($this->responder)(['actividad_id' => $id, 'calificacion' => 4, 'comentario' => 'Muy útil'])->assertCreated()
        ->assertJsonPath('data.actividad_id', $id)->assertJsonPath('data.calificacion', 4)->assertJsonPath('data.comentario', 'Muy útil')
        ->assertJsonStructure(['data' => ['fecha']]);

    $m = EncuestaSatisfaccionModel::query()->firstOrFail();
    expect($m->colaborador_id)->toBe($this->colaborador)->and($m->tenant_id)->toBe(1)->and($m->created_at)->not->toBeNull();
});

it('el comentario es opcional', function () {
    $id = ($this->actividad)();

    ($this->responder)(['actividad_id' => $id, 'calificacion' => 5])->assertCreated()->assertJsonPath('data.comentario', null);
    expect(EncuestaSatisfaccionModel::query()->first()->comentario)->toBeNull();
});

it('valida la calificación', function (mixed $calificacion) {
    $id = ($this->actividad)();

    ($this->responder)(['actividad_id' => $id, 'calificacion' => $calificacion])->assertUnprocessable()->assertJsonValidationErrors('calificacion');
    expect(EncuestaSatisfaccionModel::query()->count())->toBe(0);
})->with([[null], [0], [6], [3.5], ['abc'], [-1]]);

it('valida la actividad y el largo del comentario', function () {
    $id = ($this->actividad)();

    ($this->responder)(['calificacion' => 3])->assertUnprocessable()->assertJsonValidationErrors('actividad_id');
    ($this->responder)(['actividad_id' => $id, 'calificacion' => 3, 'comentario' => str_repeat('a', 1001)])->assertUnprocessable()->assertJsonValidationErrors('comentario');
});

it('rechaza si la actividad no ha finalizado', function () {
    $this->falsos['reloj']->fecha = '2026-03-05';
    $id = ($this->actividad)();

    ($this->responder)(['actividad_id' => $id, 'calificacion' => 4])->assertUnprocessable()->assertJsonPath('code', 'actividad_no_finalizada');
});

it('rechaza si el colaborador no estuvo presente', function (string $asistencia) {
    $id = ($this->actividad)($asistencia);

    ($this->responder)(['actividad_id' => $id, 'calificacion' => 4])->assertUnprocessable()->assertJsonPath('code', 'sin_asistencia');
})->with(['Ausente', 'Justificado']);

it('rechaza si la asistencia sigue Pendiente (sin registro)', function () {
    [, $accion] = accionEnPlan(['colaborador_ids' => [$this->colaborador, 99]]);
    $actividad = actividadDe($accion);
    registroDeAsistencia($actividad['id'], 99, 'Presente');

    ($this->responder)(['actividad_id' => $actividad['id'], 'calificacion' => 4])->assertUnprocessable()->assertJsonPath('code', 'sin_asistencia');
});

it('acepta hasta el día 30 y rechaza desde el 31 tras la fecha de fin', function () {
    $id = ($this->actividad)();

    $this->falsos['reloj']->fecha = '2026-04-13';
    ($this->responder)(['actividad_id' => $id, 'calificacion' => 4])->assertUnprocessable()->assertJsonPath('code', 'ventana_cerrada');

    $this->falsos['reloj']->fecha = '2026-04-12';
    ($this->responder)(['actividad_id' => $id, 'calificacion' => 4])->assertCreated();
});

it('solo se responde una vez y no se puede modificar', function () {
    $id = ($this->actividad)();
    ($this->responder)(['actividad_id' => $id, 'calificacion' => 4])->assertCreated();

    ($this->responder)(['actividad_id' => $id, 'calificacion' => 1])->assertUnprocessable()->assertJsonPath('code', 'ya_respondida');
    expect(EncuestaSatisfaccionModel::query()->count())->toBe(1)->and(EncuestaSatisfaccionModel::query()->first()->calificacion)->toBe(4);
});

it('no existen PUT ni DELETE sobre las encuestas', function () {
    $id = ($this->actividad)();
    ($this->responder)(['actividad_id' => $id, 'calificacion' => 4])->assertCreated();

    $this->putJson('/api/reportes/encuestas', ['actividad_id' => $id, 'calificacion' => 1])->assertStatus(405);
    $this->deleteJson('/api/reportes/encuestas/1')->assertStatus(404);
    $this->patchJson('/api/reportes/encuestas', [])->assertStatus(405);
});

it('responde 404 con una actividad inexistente', function () {
    ($this->responder)(['actividad_id' => 9999, 'calificacion' => 4])->assertNotFound()->assertJsonPath('code', 'actividad_no_encontrada');
});

it('el colaborador es siempre el del token: no puede responder por otro', function () {
    $id = ($this->actividad)();
    ($this->responder)(['actividad_id' => $id, 'calificacion' => 4, 'colaborador_id' => 99])->assertCreated();

    expect(EncuestaSatisfaccionModel::query()->first()->colaborador_id)->toBe($this->colaborador);
});

it('el administrador no puede responder la encuesta', function () {
    $id = ($this->actividad)();
    actuarComoAdminDeTenant();

    ($this->responder)(['actividad_id' => $id, 'calificacion' => 4])->assertStatus(404);
});
