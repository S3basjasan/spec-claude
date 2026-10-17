<?php

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;
use Illuminate\Support\Facades\DB;

it('crea el contenido en Borrador con código MICRO-NNNN y la primera entrada del historial', function () {
    actuarComoAdmin();

    $respuesta = $this->postJson('/api/microcontenidos', cuerpoMicro())->assertCreated();

    $id = $respuesta->json('data.id');
    expect($respuesta->json('data.estado'))->toBe('Borrador')
        ->and($respuesta->json('data.codigo'))->toBe('MICRO-'.str_pad((string) $id, 4, '0', STR_PAD_LEFT))
        ->and($respuesta->json('data.apto_conocimientos'))->toBeTrue()
        ->and($respuesta->json('data.competencias'))->toHaveCount(1);

    $historial = DB::table('microcontenido_historial')->where('microcontenido_id', $id)->get();
    expect($historial)->toHaveCount(1)
        ->and($historial[0]->estado_anterior)->toBeNull()
        ->and($historial[0]->estado_nuevo)->toBe('Borrador');
});

it('guarda como Borrador aun con datos incompletos salvo título, tipo y duración', function () {
    actuarComoAdmin();

    $this->postJson('/api/microcontenidos', ['titulo' => 'Solo lo básico', 'tipo' => 'PDF', 'duracion_minutos' => 5])->assertCreated()
        ->assertJsonPath('data.url', null)->assertJsonPath('data.competencias', []);
});

it('exige título, tipo y una duración mayor que cero', function () {
    actuarComoAdmin();

    $this->postJson('/api/microcontenidos', cuerpoMicro(['titulo' => '']))->assertUnprocessable()->assertJsonValidationErrors('titulo');
    $this->postJson('/api/microcontenidos', cuerpoMicro(['tipo' => null]))->assertUnprocessable()->assertJsonValidationErrors('tipo');
    $this->postJson('/api/microcontenidos', cuerpoMicro(['tipo' => 'Otro']))->assertUnprocessable()->assertJsonValidationErrors('tipo');
    $this->postJson('/api/microcontenidos', cuerpoMicro(['duracion_minutos' => 0]))->assertUnprocessable()->assertJsonValidationErrors('duracion_minutos');
    $this->postJson('/api/microcontenidos', cuerpoMicro(['duracion_minutos' => null]))->assertUnprocessable()->assertJsonValidationErrors('duracion_minutos');
});

it('rechaza una URL inválida y la URL en un quiz', function () {
    actuarComoAdmin();

    $this->postJson('/api/microcontenidos', cuerpoMicro(['url' => 'ftp://x.test']))->assertUnprocessable()->assertJsonValidationErrors('url');
    $this->postJson('/api/microcontenidos', cuerpoMicro(['url' => 'no es una url']))->assertUnprocessable()->assertJsonValidationErrors('url');
    $this->postJson('/api/microcontenidos', cuerpoMicro(['tipo' => 'Quiz', 'url' => 'https://x.test']))->assertUnprocessable()->assertJsonValidationErrors('url');
    $this->postJson('/api/microcontenidos', cuerpoMicro(['miniatura_url' => 'nada']))->assertUnprocessable()->assertJsonValidationErrors('miniatura_url');
});

it('rechaza competencias inexistentes o inactivas', function () {
    actuarComoAdmin();
    $inactiva = CompetenciaModel::factory()->inactiva()->create();

    $this->postJson('/api/microcontenidos', cuerpoMicro(['competencia_ids' => [$inactiva->id]]))
        ->assertUnprocessable()->assertJsonPath('code', 'competencia_inactiva');
    $this->postJson('/api/microcontenidos', cuerpoMicro(['competencia_ids' => [99999]]))
        ->assertUnprocessable()->assertJsonPath('code', 'competencia_inactiva');
});

it('registra los vínculos en el historial del Catálogo', function () {
    actuarComoAdmin();
    $competencia = CompetenciaModel::factory()->create();

    $id = $this->postJson('/api/microcontenidos', cuerpoMicro(['competencia_ids' => [$competencia->id]]))->json('data.id');

    $fila = DB::table('competencia_formativa_historial')->where('competencia_id', $competencia->id)->first();
    expect($fila->tipo_cambio)->toBe('Microcontenido')->and($fila->accion)->toBe('Asociado')->and((int) $fila->recurso_id)->toBe($id);
});

it('los códigos son distintos y no se reutilizan tras eliminar el último contenido', function () {
    actuarComoAdmin();
    $primero = $this->postJson('/api/microcontenidos', cuerpoMicro())->json('data');
    $segundo = $this->postJson('/api/microcontenidos', cuerpoMicro())->json('data');
    $this->deleteJson("/api/microcontenidos/{$segundo['id']}")->assertNoContent();
    $tercero = $this->postJson('/api/microcontenidos', cuerpoMicro())->json('data');

    expect($primero['codigo'])->not->toBe($segundo['codigo'])
        ->and($tercero['codigo'])->not->toBe($segundo['codigo'])
        ->and($tercero['id'])->toBeGreaterThan($segundo['id']);
});

it('sin sesión recibe 401 y el colaborador 403', function () {
    $this->postJson('/api/microcontenidos', cuerpoMicro())->assertUnauthorized();

    actuarComoUsuario();
    $this->postJson('/api/microcontenidos', cuerpoMicro())->assertForbidden();
});
