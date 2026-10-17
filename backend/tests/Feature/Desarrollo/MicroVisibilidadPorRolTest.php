<?php

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;

function titulosVisibles($respuesta): array
{
    return collect($respuesta->json('data'))->pluck('titulo')->sort()->values()->all();
}

it('el colaborador solo lista los publicados, sin completaciones ni acciones administrativas', function () {
    microDe(['titulo' => 'Borrador']);
    microDe(['titulo' => 'Publicado', 'estado' => 'Publicado']);
    microDe(['titulo' => 'Archivado', 'estado' => 'Archivado']);
    colaboradorConCuentaDePlan();

    $respuesta = $this->getJson('/api/microcontenidos?estado=Todos')->assertOk();

    expect(titulosVisibles($respuesta))->toBe(['Publicado'])
        ->and($respuesta->json('data.0'))->not->toHaveKey('completaciones')->not->toHaveKey('puede_eliminarse')
        ->and($respuesta->json('data.0'))->toHaveKey('progreso');
});

it('el administrador ve todos los estados con Todos', function () {
    microDe(['titulo' => 'Borrador']);
    microDe(['titulo' => 'Publicado', 'estado' => 'Publicado']);
    microDe(['titulo' => 'Archivado', 'estado' => 'Archivado']);
    actuarComoAdmin();

    expect(titulosVisibles($this->getJson('/api/microcontenidos?estado=Todos')))->toBe(['Archivado', 'Borrador', 'Publicado']);
});

it('un archivado en progreso conserva el avance y se retoma al volver a publicar', function () {
    $video = microDe(['tipo' => 'Video', 'estado' => 'Publicado']);
    [, $ficha] = colaboradorConCuentaDePlan();
    $this->putJson("/api/microcontenidos/{$video->id}/progreso", ['posicion_segundos' => 240])->assertOk();

    $video->forceFill(['estado' => 'Archivado'])->save();
    $this->getJson("/api/microcontenidos/{$video->id}")->assertNotFound();
    $this->putJson("/api/microcontenidos/{$video->id}/progreso", ['posicion_segundos' => 300])->assertNotFound();

    $video->forceFill(['estado' => 'Publicado'])->save();
    $this->getJson("/api/microcontenidos/{$video->id}")->assertOk()->assertJsonPath('data.progreso.posicion_segundos', 240)
        ->assertJsonPath('data.progreso.estado', 'EnProgreso');
});

it('editar un publicado no cambia su estado', function () {
    $micro = microDe(['estado' => 'Publicado', 'tipo' => 'Video']);
    actuarComoAdmin();

    $this->putJson("/api/microcontenidos/{$micro->id}", cuerpoMicro(['titulo' => 'Título nuevo', 'duracion_minutos' => 30, 'miniatura_url' => 'https://img.test/a.png']))
        ->assertOk()->assertJsonPath('data.estado', 'Publicado')->assertJsonPath('data.titulo', 'Título nuevo')
        ->assertJsonPath('data.miniatura_url', 'https://img.test/a.png');
});

it('las competencias editadas aparecen en el historial del Catálogo', function () {
    $nueva = CompetenciaModel::factory()->create();
    $micro = microDe(['estado' => 'Publicado', 'tipo' => 'Video']);
    actuarComoAdmin();

    $this->putJson("/api/microcontenidos/{$micro->id}", cuerpoMicro(['competencia_ids' => [$nueva->id]]))->assertOk();
    $historial = $this->getJson("/api/catalogo-formativo/{$nueva->id}/historial")->assertOk()->json('data');

    expect($historial[0]['tipo_cambio'])->toBe('Microcontenido')->and($historial[0]['accion'])->toBe('Asociado');
});
