<?php

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\ComportamientoModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\PreguntaModel;
use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Laravel\Sanctum\Sanctum;

function comportamientosNuevos(string $prefijo = 'N'): array
{
    return array_map(
        fn (string $g) => array_map(fn (int $n) => "$prefijo$g$n", [1, 2, 3, 4]),
        ['A', 'B', 'C', 'D'],
    );
}

it('actualiza los 16 comportamientos sin insertar ni borrar filas y conserva el autor original', function () {
    $creador = UserModel::factory()->admin()->create();
    $competencia = CompetenciaModel::factory()->create();
    ComportamientoModel::query()->update(['created_by' => $creador->id]);
    $ids = ComportamientoModel::query()->orderBy('id')->pluck('id')->all();
    $editor = UserModel::factory()->admin()->create();
    Sanctum::actingAs($editor, ['admin']);

    $this->putJson("/api/competencias/{$competencia->id}/comportamientos", [
        'version' => 1,
        'comportamientos' => comportamientosNuevos(),
    ])
        ->assertOk()
        ->assertJsonPath('data.version', 2)
        ->assertJsonPath('data.grados.0.comportamientos', ['NA1', 'NA2', 'NA3', 'NA4'])
        ->assertJsonPath('data.grados.3.comportamientos', ['ND1', 'ND2', 'ND3', 'ND4']);

    expect(ComportamientoModel::query()->orderBy('id')->pluck('id')->all())->toBe($ids)
        ->and(ComportamientoModel::query()->where('created_by', $creador->id)->count())->toBe(16)
        ->and(ComportamientoModel::query()->where('updated_by', $editor->id)->count())->toBe(16)
        ->and($competencia->fresh()->updated_by)->toBe($editor->id);
});

it('actualiza las cuatro preguntas sin insertar ni borrar filas', function () {
    $competencia = CompetenciaModel::factory()->create();
    $ids = PreguntaModel::query()->orderBy('id')->pluck('id')->all();
    actuarComoAdmin();

    $this->putJson("/api/competencias/{$competencia->id}/preguntas", [
        'version' => 1,
        'preguntas' => ['Nueva 1', 'Nueva 2', 'Nueva 3', 'Nueva 4'],
    ])
        ->assertOk()
        ->assertJsonPath('data.version', 2)
        ->assertJsonPath('data.preguntas', ['Nueva 1', 'Nueva 2', 'Nueva 3', 'Nueva 4']);

    expect(PreguntaModel::query()->orderBy('id')->pluck('id')->all())->toBe($ids);
});

it('completa una competencia incompleta al guardar comportamientos y preguntas', function () {
    $incompleta = CompetenciaModel::factory()->incompleta()->create();
    actuarComoAdmin();

    $this->putJson("/api/competencias/{$incompleta->id}/comportamientos", ['version' => 1, 'comportamientos' => comportamientosNuevos()])
        ->assertOk()->assertJsonPath('data.faltantes', ['preguntas']);

    $this->putJson("/api/competencias/{$incompleta->id}/preguntas", ['version' => 2, 'preguntas' => ['a', 'b', 'c', 'd']])
        ->assertOk()->assertJsonPath('data.completa', true);

    expect(ComportamientoModel::query()->count())->toBe(16)
        ->and(PreguntaModel::query()->count())->toBe(4);
});

it('valida los textos de los comportamientos y de las preguntas', function () {
    $competencia = CompetenciaModel::factory()->create();
    actuarComoAdmin();
    $comportamientos = comportamientosNuevos();
    $comportamientos[0][0] = str_repeat('c', 301);
    $comportamientos[1][1] = '   ';
    unset($comportamientos[3][3]);

    $respuesta = $this->putJson("/api/competencias/{$competencia->id}/comportamientos", ['version' => 1, 'comportamientos' => $comportamientos])
        ->assertUnprocessable();
    expect($respuesta->json('errors')['comportamientos.0.0'][0])->toBe('Un comportamiento no puede superar los 300 caracteres.')
        ->and($respuesta->json('errors')['comportamientos.1.1'][0])->toBe('Completa los cuatro comportamientos de cada grado.')
        ->and($respuesta->json('errors')['comportamientos.3'][0])->toBe('Completa los cuatro comportamientos de cada grado.');

    $respuesta = $this->putJson("/api/competencias/{$competencia->id}/preguntas", [
        'version' => 1,
        'preguntas' => ['ok', str_repeat('p', 501), '', 'ok'],
    ])->assertUnprocessable();
    expect($respuesta->json('errors')['preguntas.1'][0])->toBe('Una pregunta no puede superar los 500 caracteres.')
        ->and($respuesta->json('errors')['preguntas.2'][0])->toBe('Completa las cuatro preguntas.');

    expect($competencia->fresh()->version)->toBe(1);
});

it('permite editar los comportamientos y las preguntas de una competencia inactiva', function () {
    $inactiva = CompetenciaModel::factory()->inactiva()->create();
    actuarComoAdmin();

    $this->putJson("/api/competencias/{$inactiva->id}/comportamientos", ['version' => 1, 'comportamientos' => comportamientosNuevos()])
        ->assertOk()->assertJsonPath('data.estado', 'Inactivo');
    $this->putJson("/api/competencias/{$inactiva->id}/preguntas", ['version' => 2, 'preguntas' => ['a', 'b', 'c', 'd']])
        ->assertOk()->assertJsonPath('data.estado', 'Inactivo');
});

it('rechaza con 401, 403 y 404', function () {
    $competencia = CompetenciaModel::factory()->create();
    $cuerpo = ['version' => 1, 'preguntas' => ['a', 'b', 'c', 'd']];

    $this->putJson("/api/competencias/{$competencia->id}/preguntas", $cuerpo)->assertUnauthorized();
    $this->putJson("/api/competencias/{$competencia->id}/comportamientos", ['version' => 1, 'comportamientos' => comportamientosNuevos()])->assertUnauthorized();

    actuarComoUsuario();
    $this->putJson("/api/competencias/{$competencia->id}/preguntas", $cuerpo)->assertForbidden();
    $this->putJson("/api/competencias/{$competencia->id}/comportamientos", ['version' => 1, 'comportamientos' => comportamientosNuevos()])->assertForbidden();

    actuarComoAdmin();
    $this->putJson('/api/competencias/99999/preguntas', $cuerpo)->assertNotFound();
    $this->putJson('/api/competencias/99999/comportamientos', ['version' => 1, 'comportamientos' => comportamientosNuevos()])->assertNotFound();
});
