<?php

use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\MicroProgresoModel;

it('guarda el avance creando el progreso En progreso con fecha de inicio', function () {
    $video = microDe(['tipo' => 'Video', 'estado' => 'Publicado']);
    [, $ficha] = colaboradorConCuentaDePlan();

    $this->putJson("/api/microcontenidos/{$video->id}/progreso", ['posicion_segundos' => 95])
        ->assertOk()->assertJsonPath('data.estado', 'EnProgreso')->assertJsonPath('data.posicion_segundos', 95);

    $progreso = MicroProgresoModel::query()->where('colaborador_id', $ficha)->first();
    expect($progreso->microcontenido_id)->toBe($video->id)->and($progreso->fecha_inicio)->not->toBeNull()->and($progreso->tenant_id)->toBe(1);

    $this->putJson("/api/microcontenidos/{$video->id}/progreso", ['posicion_segundos' => 130])->assertOk();
    expect(MicroProgresoModel::query()->count())->toBe(1)->and($progreso->fresh()->posicion_segundos)->toBe(130);
});

it('la posición solo se guarda en un video', function () {
    $pdf = microDe(['tipo' => 'PDF', 'estado' => 'Publicado']);
    colaboradorConCuentaDePlan();

    $this->putJson("/api/microcontenidos/{$pdf->id}/progreso", ['posicion_segundos' => 50])->assertOk()->assertJsonPath('data.posicion_segundos', null);
});

it('completa un video, PDF o artículo con fecha de finalización', function (string $tipo) {
    $micro = microDe(['tipo' => $tipo, 'estado' => 'Publicado']);
    [, $ficha] = colaboradorConCuentaDePlan();

    $this->postJson("/api/microcontenidos/{$micro->id}/completar")->assertOk()->assertJsonPath('data.estado', 'Completado');

    $progreso = MicroProgresoModel::query()->where('colaborador_id', $ficha)->first();
    expect($progreso->estado)->toBe('Completado')->and($progreso->fecha_completado)->not->toBeNull();
})->with(['Video', 'PDF', 'Articulo']);

it('rechaza avanzar o completar un contenido ya completado, un quiz o uno no publicado', function () {
    $completo = microDe(['tipo' => 'Video', 'estado' => 'Publicado']);
    $quiz = quizDe(1, ['estado' => 'Publicado']);
    $archivado = microDe(['tipo' => 'Video', 'estado' => 'Archivado']);
    [, $ficha] = colaboradorConCuentaDePlan();
    progresoMicro($completo->id, $ficha, 'Completado');
    progresoMicro($archivado->id, $ficha, 'Completado');

    $this->postJson("/api/microcontenidos/{$completo->id}/completar")->assertUnprocessable()->assertJsonPath('code', 'ya_completado');
    $this->putJson("/api/microcontenidos/{$completo->id}/progreso", ['posicion_segundos' => 3])->assertUnprocessable()->assertJsonPath('code', 'ya_completado');
    $this->postJson("/api/microcontenidos/{$quiz->id}/completar")->assertUnprocessable()->assertJsonPath('code', 'no_disponible');
    $this->putJson("/api/microcontenidos/{$quiz->id}/progreso", ['posicion_segundos' => 3])->assertUnprocessable()->assertJsonPath('code', 'no_disponible');
    $this->postJson("/api/microcontenidos/{$archivado->id}/completar")->assertUnprocessable();
});

it('un borrador, un archivado no completado o un id inexistente responden 404', function () {
    $borrador = microDe(['tipo' => 'Video']);
    $archivado = microDe(['tipo' => 'Video', 'estado' => 'Archivado']);
    colaboradorConCuentaDePlan();

    $this->putJson("/api/microcontenidos/{$borrador->id}/progreso", [])->assertNotFound();
    $this->postJson("/api/microcontenidos/{$borrador->id}/completar")->assertNotFound();
    $this->putJson("/api/microcontenidos/{$archivado->id}/progreso", [])->assertNotFound();
    $this->postJson('/api/microcontenidos/9999/completar')->assertNotFound();
});

it('el administrador y un usuario sin ficha reciben 403, y sin sesión 401', function () {
    $micro = microDe(['tipo' => 'Video', 'estado' => 'Publicado']);

    $this->postJson("/api/microcontenidos/{$micro->id}/completar")->assertUnauthorized();

    actuarComoAdmin();
    $this->postJson("/api/microcontenidos/{$micro->id}/completar")->assertForbidden();
    $this->putJson("/api/microcontenidos/{$micro->id}/progreso", [])->assertForbidden();

    actuarComoUsuario();
    $this->postJson("/api/microcontenidos/{$micro->id}/completar")->assertForbidden();
});

it('cada colaborador solo opera sobre su propio progreso', function () {
    $micro = microDe(['tipo' => 'Video', 'estado' => 'Publicado']);
    $ajeno = progresoMicro($micro->id, 99999, 'EnProgreso', 1, 500);
    [, $ficha] = colaboradorConCuentaDePlan();

    $this->putJson("/api/microcontenidos/{$micro->id}/progreso", ['posicion_segundos' => 10])->assertOk();

    expect($ajeno->fresh()->posicion_segundos)->toBe(500)
        ->and(MicroProgresoModel::query()->where('colaborador_id', $ficha)->value('posicion_segundos'))->toBe(10);
});

it('el detalle del colaborador trae su progreso y nunca la opción correcta ni las completaciones', function () {
    $quiz = quizDe(2, ['estado' => 'Publicado']);
    [, $ficha] = colaboradorConCuentaDePlan();
    $progreso = progresoMicro($quiz->id, $ficha, 'Completado');
    intentoMicro($progreso->id, 'Finalizado', 2, 1);

    $datos = $this->getJson("/api/microcontenidos/{$quiz->id}")->assertOk()->json('data');

    expect($datos['progreso']['estado'])->toBe('Completado')
        ->and($datos['progreso']['ultimo_puntaje'])->toBe(['correctas' => 1, 'total' => 2])
        ->and($datos)->not->toHaveKey('completaciones')
        ->and($datos['preguntas'][0]['opciones'][0])->not->toHaveKey('es_correcta');
});

it('un archivado completado sigue consultable; un borrador no', function () {
    $archivado = microDe(['tipo' => 'PDF', 'estado' => 'Archivado']);
    $borrador = microDe(['tipo' => 'PDF']);
    [, $ficha] = colaboradorConCuentaDePlan();
    progresoMicro($archivado->id, $ficha, 'Completado');

    $this->getJson("/api/microcontenidos/{$archivado->id}")->assertOk();
    $this->getJson("/api/microcontenidos/{$borrador->id}")->assertNotFound();
});
