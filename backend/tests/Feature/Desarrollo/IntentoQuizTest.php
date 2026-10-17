<?php

use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\MicrocontenidoModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\MicroIntentoModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\MicroProgresoModel;

/** Id de una opción de la pregunta (índice desde 0) del quiz: la correcta o una incorrecta. */
function opcionDe(MicrocontenidoModel $quiz, int $pregunta, bool $correcta): array
{
    $p = $quiz->fresh(['preguntas.opciones'])->preguntas[$pregunta];

    return [$p->id, $p->opciones->firstWhere('es_correcta', $correcta)->id];
}

function responderQuiz($prueba, int $intentoId, array $par)
{
    return $prueba->postJson("/api/microcontenidos/intentos/$intentoId/respuestas", ['pregunta_id' => $par[0], 'opcion_id' => $par[1]]);
}

it('inicia un intento creando el progreso y sin revelar la opción correcta', function () {
    $quiz = quizDe(3, ['estado' => 'Publicado']);
    [, $ficha] = colaboradorConCuentaDePlan();

    $respuesta = $this->postJson("/api/microcontenidos/{$quiz->id}/intentos")->assertCreated();

    expect($respuesta->json('data.estado'))->toBe('EnCurso')
        ->and($respuesta->json('data.total_preguntas'))->toBe(3)
        ->and($respuesta->json('data.preguntas'))->toHaveCount(3)
        ->and($respuesta->json('data.preguntas.0.opciones.0'))->not->toHaveKey('es_correcta');
    expect(MicroProgresoModel::query()->where('colaborador_id', $ficha)->value('estado'))->toBe('EnProgreso');
});

it('devuelve el intento vigente con lo ya respondido y reinicia con reiniciar', function () {
    $quiz = quizDe(2, ['estado' => 'Publicado']);
    colaboradorConCuentaDePlan();
    $id = $this->postJson("/api/microcontenidos/{$quiz->id}/intentos")->json('data.id');
    responderQuiz($this, $id, opcionDe($quiz, 0, true))->assertOk();

    $vigente = $this->postJson("/api/microcontenidos/{$quiz->id}/intentos")->assertOk();
    expect($vigente->json('data.id'))->toBe($id)->and($vigente->json('data.respondidas'))->toHaveCount(1);

    $nuevo = $this->postJson("/api/microcontenidos/{$quiz->id}/intentos", ['reiniciar' => true])->assertCreated();
    expect($nuevo->json('data.id'))->not->toBe($id)->and($nuevo->json('data.respondidas'))->toBe([])
        ->and(MicroIntentoModel::query()->count())->toBe(1);
});

it('responde con retroalimentación inmediata y finaliza el intento al responder la última pregunta', function () {
    $quiz = quizDe(3, ['estado' => 'Publicado']);
    [, $ficha] = colaboradorConCuentaDePlan();
    $id = $this->postJson("/api/microcontenidos/{$quiz->id}/intentos")->json('data.id');

    $primera = responderQuiz($this, $id, opcionDe($quiz, 0, true))->assertOk();
    expect($primera->json('data.es_correcta'))->toBeTrue()
        ->and($primera->json('data.opcion_correcta_id'))->toBe(opcionDe($quiz, 0, true)[1])
        ->and($primera->json('data.intento.estado'))->toBe('EnCurso')
        ->and(MicroProgresoModel::query()->where('colaborador_id', $ficha)->value('estado'))->toBe('EnProgreso');

    $segunda = responderQuiz($this, $id, opcionDe($quiz, 1, false))->assertOk();
    expect($segunda->json('data.es_correcta'))->toBeFalse()
        ->and($segunda->json('data.opcion_correcta_id'))->toBe(opcionDe($quiz, 1, true)[1]);

    $ultima = responderQuiz($this, $id, opcionDe($quiz, 2, true))->assertOk();
    expect($ultima->json('data.intento.estado'))->toBe('Finalizado')
        ->and($ultima->json('data.intento.puntaje'))->toBe(2)
        ->and($ultima->json('data.completado_ahora'))->toBeTrue();

    $progreso = MicroProgresoModel::query()->where('colaborador_id', $ficha)->first();
    expect($progreso->estado)->toBe('Completado')->and($progreso->fecha_completado)->not->toBeNull();
});

it('repetir el quiz con menor puntaje no revierte Completado y registra otro intento', function () {
    $quiz = quizDe(2, ['estado' => 'Publicado']);
    [, $ficha] = colaboradorConCuentaDePlan();

    $primero = $this->postJson("/api/microcontenidos/{$quiz->id}/intentos")->json('data.id');
    responderQuiz($this, $primero, opcionDe($quiz, 0, true));
    responderQuiz($this, $primero, opcionDe($quiz, 1, true));

    $segundo = $this->postJson("/api/microcontenidos/{$quiz->id}/intentos")->assertCreated()->json('data.id');
    responderQuiz($this, $segundo, opcionDe($quiz, 0, false));
    $fin = responderQuiz($this, $segundo, opcionDe($quiz, 1, false));

    expect($fin->json('data.intento.puntaje'))->toBe(0)->and($fin->json('data.completado_ahora'))->toBeFalse()
        ->and(MicroIntentoModel::query()->where('estado', 'Finalizado')->count())->toBe(2)
        ->and(MicroProgresoModel::query()->where('colaborador_id', $ficha)->value('estado'))->toBe('Completado');
});

it('un intento a medias no completa el contenido ni cuenta como terminado', function () {
    $quiz = quizDe(3, ['estado' => 'Publicado']);
    [, $ficha] = colaboradorConCuentaDePlan();
    $id = $this->postJson("/api/microcontenidos/{$quiz->id}/intentos")->json('data.id');
    responderQuiz($this, $id, opcionDe($quiz, 0, true));

    expect(MicroProgresoModel::query()->where('colaborador_id', $ficha)->value('estado'))->toBe('EnProgreso')
        ->and(MicroIntentoModel::query()->where('estado', 'Finalizado')->count())->toBe(0);

    actuarComoAdmin();
    $this->getJson("/api/microcontenidos/{$quiz->id}")->assertJsonPath('data.tiene_intentos', false);
});

it('rechaza responder dos veces, una opción ajena a la pregunta y un intento ya finalizado', function () {
    $quiz = quizDe(1, ['estado' => 'Publicado']);
    $otro = quizDe(1, ['estado' => 'Publicado']);
    colaboradorConCuentaDePlan();
    $id = $this->postJson("/api/microcontenidos/{$quiz->id}/intentos")->json('data.id');

    [$pregunta] = opcionDe($quiz, 0, true);
    $this->postJson("/api/microcontenidos/intentos/$id/respuestas", ['pregunta_id' => $pregunta, 'opcion_id' => opcionDe($otro, 0, true)[1]])
        ->assertUnprocessable()->assertJsonPath('code', 'respuesta_invalida')->assertJsonPath('errors.opcion_id.0', 'La opción no pertenece a la pregunta.');
    $this->postJson("/api/microcontenidos/intentos/$id/respuestas", ['pregunta_id' => opcionDe($otro, 0, true)[0], 'opcion_id' => opcionDe($otro, 0, true)[1]])
        ->assertUnprocessable()->assertJsonPath('code', 'respuesta_invalida');

    responderQuiz($this, $id, opcionDe($quiz, 0, true))->assertOk()->assertJsonPath('data.intento.estado', 'Finalizado');
    responderQuiz($this, $id, opcionDe($quiz, 0, true))->assertUnprocessable()->assertJsonPath('code', 'intento_descartado');
});

it('rechaza responder dos veces la misma pregunta', function () {
    $quiz = quizDe(2, ['estado' => 'Publicado']);
    colaboradorConCuentaDePlan();
    $id = $this->postJson("/api/microcontenidos/{$quiz->id}/intentos")->json('data.id');

    responderQuiz($this, $id, opcionDe($quiz, 0, true))->assertOk();
    responderQuiz($this, $id, opcionDe($quiz, 0, false))->assertUnprocessable()->assertJsonPath('code', 'respuesta_invalida');
});

it('no inicia intentos en contenidos que no son un quiz publicado', function () {
    $video = microDe(['tipo' => 'Video', 'estado' => 'Publicado']);
    $archivado = quizDe(1, ['estado' => 'Archivado']);
    $borrador = quizDe(1);
    colaboradorConCuentaDePlan();

    $this->postJson("/api/microcontenidos/{$video->id}/intentos")->assertUnprocessable()->assertJsonPath('code', 'no_disponible');
    $this->postJson("/api/microcontenidos/{$archivado->id}/intentos")->assertNotFound();
    $this->postJson("/api/microcontenidos/{$borrador->id}/intentos")->assertNotFound();
    $this->postJson('/api/microcontenidos/9999/intentos')->assertNotFound();
});

it('un intento ajeno responde 404 y el administrador 403', function () {
    $quiz = quizDe(1, ['estado' => 'Publicado']);
    $progresoAjeno = progresoMicro($quiz->id, 99999);
    $ajeno = intentoMicro($progresoAjeno->id, 'EnCurso', 1);
    colaboradorConCuentaDePlan();

    responderQuiz($this, $ajeno->id, opcionDe($quiz, 0, true))->assertNotFound();
    responderQuiz($this, 99999, opcionDe($quiz, 0, true))->assertNotFound();

    actuarComoAdmin();
    responderQuiz($this, $ajeno->id, opcionDe($quiz, 0, true))->assertForbidden();
    $this->postJson("/api/microcontenidos/{$quiz->id}/intentos")->assertForbidden();
});

it('sin sesión responde 401', function () {
    $this->postJson('/api/microcontenidos/1/intentos')->assertUnauthorized();
    $this->postJson('/api/microcontenidos/intentos/1/respuestas', [])->assertUnauthorized();
});
