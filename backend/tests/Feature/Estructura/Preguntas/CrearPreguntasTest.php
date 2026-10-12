<?php

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\PreguntaModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\RegistroPreguntasModel;
use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Laravel\Sanctum\Sanctum;

it('crea el registro como Administrador con versión 1 y las cuatro preguntas en orden', function () {
    actuarComoAdmin();
    $competencia = CompetenciaModel::factory()->create(['nombre' => 'Liderazgo']);

    $this->postJson('/api/preguntas', datosPreguntas($competencia->id))
        ->assertCreated()
        ->assertJsonPath('data.competencia_id', $competencia->id)
        ->assertJsonPath('data.nombre', 'Liderazgo')
        ->assertJsonPath('data.version', 1)
        ->assertJsonPath('data.preguntas.0', 'Cuéntame de una meta que lograste.')
        ->assertJsonPath('data.preguntas.3', '¿Cómo trabajas bajo presión?');

    expect(PreguntaModel::query()->count())->toBe(4);
});

it('registra quién lo creó', function () {
    $admin = UserModel::factory()->admin()->create();
    Sanctum::actingAs($admin, ['admin']);
    $competencia = CompetenciaModel::factory()->create();

    $this->postJson('/api/preguntas', datosPreguntas($competencia->id))->assertCreated();

    $registro = RegistroPreguntasModel::query()->first();
    expect($registro->created_by)->toBe($admin->id)->and($registro->updated_by)->toBeNull();
});

it('rechaza con 401 sin sesión y con 403 al Usuario', function () {
    $competencia = CompetenciaModel::factory()->create();
    $this->postJson('/api/preguntas', datosPreguntas($competencia->id))->assertUnauthorized();

    actuarComoUsuario();
    $this->postJson('/api/preguntas', datosPreguntas($competencia->id))
        ->assertForbidden()
        ->assertExactJson(['message' => 'No tienes permisos para realizar esta acción.']);
    expect(RegistroPreguntasModel::query()->count())->toBe(0);
});

it('devuelve 422 sin competencia o con una inexistente, inactiva o que ya tiene registro', function () {
    actuarComoAdmin();
    $inactiva = CompetenciaModel::factory()->inactiva()->create();
    $conRegistro = registroPreguntasDe();

    $this->postJson('/api/preguntas', datosPreguntas(0, ['competencia_id' => null]))
        ->assertUnprocessable()->assertJsonValidationErrors(['competencia_id']);
    $this->postJson('/api/preguntas', datosPreguntas(999))
        ->assertUnprocessable()->assertJsonValidationErrors(['competencia_id']);
    $this->postJson('/api/preguntas', datosPreguntas($inactiva->id))
        ->assertUnprocessable()->assertJsonValidationErrors(['competencia_id']);
    $this->postJson('/api/preguntas', datosPreguntas($conRegistro->id))
        ->assertUnprocessable()->assertJsonValidationErrors(['competencia_id'])
        ->assertJsonPath('errors.competencia_id.0', 'La competencia ya tiene un registro de preguntas.');

    expect(RegistroPreguntasModel::query()->count())->toBe(1);
});

it('exige exactamente cuatro preguntas', function () {
    actuarComoAdmin();
    $competencia = CompetenciaModel::factory()->create();
    $tres = ['Uno', 'Dos', 'Tres'];
    $cinco = ['Uno', 'Dos', 'Tres', 'Cuatro', 'Cinco'];

    $this->postJson('/api/preguntas', datosPreguntas($competencia->id, ['preguntas' => []]))->assertUnprocessable();
    $this->postJson('/api/preguntas', datosPreguntas($competencia->id, ['preguntas' => $tres]))
        ->assertUnprocessable()->assertJsonValidationErrors(['preguntas']);
    $this->postJson('/api/preguntas', datosPreguntas($competencia->id, ['preguntas' => $cinco]))
        ->assertUnprocessable()->assertJsonValidationErrors(['preguntas']);
    expect(RegistroPreguntasModel::query()->count())->toBe(0);
});

it('rechaza textos vacíos, de más de 500 caracteres o repetidos en el registro', function () {
    actuarComoAdmin();
    $competencia = CompetenciaModel::factory()->create();

    $this->postJson('/api/preguntas', datosPreguntas($competencia->id, ['preguntas' => ['   ', str_repeat('x', 501), 'Igual', ' igual ']]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['preguntas.0', 'preguntas.1', 'preguntas.3']);
    expect(RegistroPreguntasModel::query()->count())->toBe(0);
});

it('acepta exactamente 500 caracteres', function () {
    actuarComoAdmin();
    $competencia = CompetenciaModel::factory()->create();

    $this->postJson('/api/preguntas', datosPreguntas($competencia->id, ['preguntas' => [str_repeat('x', 500), 'B', 'C', 'D']]))
        ->assertCreated();
});

it('recorta los espacios de cada texto al guardar', function () {
    actuarComoAdmin();
    $competencia = CompetenciaModel::factory()->create();

    $this->postJson('/api/preguntas', datosPreguntas($competencia->id, ['preguntas' => ['  A  ', 'B', 'C', 'D']]))
        ->assertCreated()
        ->assertJsonPath('data.preguntas.0', 'A');
});

it('dos creaciones de la misma competencia dejan un solo registro', function () {
    actuarComoAdmin();
    $competencia = CompetenciaModel::factory()->create();

    $this->postJson('/api/preguntas', datosPreguntas($competencia->id))->assertCreated();
    $this->postJson('/api/preguntas', datosPreguntas($competencia->id))->assertUnprocessable();

    expect(RegistroPreguntasModel::query()->count())->toBe(1)->and(PreguntaModel::query()->count())->toBe(4);
});
