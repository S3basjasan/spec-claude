<?php

use Illuminate\Support\Facades\DB;

it('elimina un contenido sin progreso con sus preguntas, opciones, historial y vínculos', function () {
    $quiz = quizDe(2, ['estado' => 'Publicado']);
    $competencia = DB::table('competencia_microcontenido')->where('microcontenido_id', $quiz->id)->value('competencia_id');
    actuarComoAdmin();

    $this->deleteJson("/api/microcontenidos/{$quiz->id}")->assertNoContent();

    expect(DB::table('microcontenidos')->where('id', $quiz->id)->exists())->toBeFalse()
        ->and(DB::table('microcontenido_preguntas')->where('microcontenido_id', $quiz->id)->count())->toBe(0)
        ->and(DB::table('microcontenido_opciones')->count())->toBe(0)
        ->and(DB::table('microcontenido_historial')->where('microcontenido_id', $quiz->id)->count())->toBe(0)
        ->and(DB::table('competencia_microcontenido')->where('microcontenido_id', $quiz->id)->count())->toBe(0);

    $quitado = DB::table('competencia_formativa_historial')->where('competencia_id', $competencia)->where('accion', 'Quitado')->first();
    expect($quitado->tipo_cambio)->toBe('Microcontenido')->and((int) $quitado->recurso_id)->toBe($quiz->id);
});

it('no elimina un contenido con progreso, aunque esté solo en progreso, e indica archivarlo', function (string $estado) {
    $micro = microDe(['estado' => 'Publicado']);
    progresoMicro($micro->id, 5, $estado);
    actuarComoAdmin();

    $this->deleteJson("/api/microcontenidos/{$micro->id}")->assertUnprocessable()->assertJsonPath('code', 'con_progreso');

    expect(DB::table('microcontenidos')->where('id', $micro->id)->exists())->toBeTrue();
    $this->getJson("/api/microcontenidos/{$micro->id}")->assertJsonPath('data.puede_eliminarse', false);
})->with(['EnProgreso', 'Completado']);

it('el detalle indica que un contenido sin progreso puede eliminarse', function () {
    $micro = microDe();
    actuarComoAdmin();

    $this->getJson("/api/microcontenidos/{$micro->id}")->assertJsonPath('data.puede_eliminarse', true);
});

it('responde 404 si no existe, 403 al colaborador y 401 sin sesión', function () {
    $micro = microDe();
    $this->deleteJson("/api/microcontenidos/{$micro->id}")->assertUnauthorized();

    actuarComoUsuario();
    $this->deleteJson("/api/microcontenidos/{$micro->id}")->assertForbidden();

    actuarComoAdmin();
    $this->deleteJson('/api/microcontenidos/9999')->assertNotFound();
});
