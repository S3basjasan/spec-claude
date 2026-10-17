<?php

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;
use Illuminate\Support\Facades\DB;

it('publica un borrador completo', function () {
    $micro = microDe(['tipo' => 'Video']);
    actuarComoAdmin();

    $this->patchJson("/api/microcontenidos/{$micro->id}/estado", ['estado' => 'Publicado'])->assertOk()->assertJsonPath('data.estado', 'Publicado');
});

it('no publica con datos faltantes e informa cuáles', function () {
    $micro = microDe(['tipo' => 'Articulo', 'url' => null], []);
    actuarComoAdmin();

    $this->patchJson("/api/microcontenidos/{$micro->id}/estado", ['estado' => 'Publicado'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'faltan_requisitos')
        ->assertJsonPath('faltantes', ['url', 'competencias']);
});

it('un quiz sin preguntas no se publica y con preguntas sí', function () {
    $vacio = microDe(['tipo' => 'Quiz', 'url' => null]);
    $completo = quizDe(2);
    actuarComoAdmin();

    $this->patchJson("/api/microcontenidos/{$vacio->id}/estado", ['estado' => 'Publicado'])->assertUnprocessable()->assertJsonPath('faltantes', ['preguntas']);
    $this->patchJson("/api/microcontenidos/{$completo->id}/estado", ['estado' => 'Publicado'])->assertOk();
});

it('una competencia inactiva no cuenta para publicar', function () {
    $inactiva = CompetenciaModel::factory()->inactiva()->create();
    $micro = microDe([], [$inactiva->id]);
    actuarComoAdmin();

    $this->patchJson("/api/microcontenidos/{$micro->id}/estado", ['estado' => 'Publicado'])->assertUnprocessable()->assertJsonPath('faltantes', ['competencias']);
});

it('archiva, reactiva y deja una entrada de historial por cada cambio', function () {
    $micro = microDe(['estado' => 'Publicado']);
    actuarComoAdmin();

    $this->patchJson("/api/microcontenidos/{$micro->id}/estado", ['estado' => 'Archivado'])->assertOk()->assertJsonPath('data.estado', 'Archivado');
    $this->patchJson("/api/microcontenidos/{$micro->id}/estado", ['estado' => 'Publicado'])->assertOk()->assertJsonPath('data.estado', 'Publicado');

    $pares = DB::table('microcontenido_historial')->where('microcontenido_id', $micro->id)->orderBy('id')
        ->get(['estado_anterior', 'estado_nuevo'])->map(fn ($h) => [$h->estado_anterior, $h->estado_nuevo])->all();
    expect($pares)->toBe([['Publicado', 'Archivado'], ['Archivado', 'Publicado']]);
});

it('rechaza las transiciones no permitidas y repetir el estado', function () {
    $borrador = microDe();
    $publicado = microDe(['estado' => 'Publicado']);
    actuarComoAdmin();

    $this->patchJson("/api/microcontenidos/{$borrador->id}/estado", ['estado' => 'Archivado'])->assertUnprocessable()->assertJsonPath('code', 'transicion_invalida');
    $this->patchJson("/api/microcontenidos/{$publicado->id}/estado", ['estado' => 'Publicado'])->assertUnprocessable()->assertJsonPath('code', 'transicion_invalida');
    $this->patchJson("/api/microcontenidos/{$publicado->id}/estado", ['estado' => 'Borrador'])->assertUnprocessable()->assertJsonValidationErrors('estado');
});

it('responde 404 si no existe y 403 al colaborador', function () {
    actuarComoAdmin();
    $this->patchJson('/api/microcontenidos/9999/estado', ['estado' => 'Publicado'])->assertNotFound();

    $micro = microDe();
    actuarComoUsuario();
    $this->patchJson("/api/microcontenidos/{$micro->id}/estado", ['estado' => 'Publicado'])->assertForbidden();
});
