<?php

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;
use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Laravel\Sanctum\Sanctum;

it('actualiza con la versión actual, incrementa la versión y conserva código y estado', function () {
    $competencia = CompetenciaModel::factory()->create(['nombre' => 'Original']);
    actuarComoAdmin();

    $this->putJson("/api/competencias/{$competencia->id}", datosCompetencia(['nombre' => 'Cambiada', 'tipo' => 'Especifica', 'version' => 1]))
        ->assertOk()
        ->assertJsonPath('data.nombre', 'Cambiada')
        ->assertJsonPath('data.tipo', 'Especifica')
        ->assertJsonPath('data.version', 2)
        ->assertJsonPath('data.codigo', 'COMP-0001')
        ->assertJsonPath('data.estado', 'Activo')
        ->assertJsonPath('data.grados.3.descripcion', 'Aplica las ideas que otros proponen.');
});

it('edita una competencia inactiva sin cambiar su estado', function () {
    $competencia = CompetenciaModel::factory()->inactiva()->create(['nombre' => 'Retirada']);
    actuarComoAdmin();

    $this->putJson("/api/competencias/{$competencia->id}", datosCompetencia(['nombre' => 'Retirada y corregida', 'version' => 1, 'estado' => 'Activo']))
        ->assertOk()
        ->assertJsonPath('data.nombre', 'Retirada y corregida')
        ->assertJsonPath('data.estado', 'Inactivo')
        ->assertJsonPath('data.codigo', 'COMP-0001');
});

it('ignora el código enviado', function () {
    $competencia = CompetenciaModel::factory()->create();
    actuarComoAdmin();

    $this->putJson("/api/competencias/{$competencia->id}", datosCompetencia(['version' => 1, 'codigo' => 'ZZZ-1']))
        ->assertOk()
        ->assertJsonPath('data.codigo', 'COMP-0001');
});

it('registra quién la modificó sin alterar quién la creó', function () {
    $creador = UserModel::factory()->admin()->create();
    $editor = UserModel::factory()->admin()->create();
    $competencia = CompetenciaModel::factory()->create(['created_by' => $creador->id]);
    Sanctum::actingAs($editor, ['admin']);

    $this->putJson("/api/competencias/{$competencia->id}", datosCompetencia(['version' => 1]))->assertOk();

    $competencia->refresh();
    expect($competencia->created_by)->toBe($creador->id)
        ->and($competencia->updated_by)->toBe($editor->id);
});

it('rechaza con 401 sin sesión y con 403 al Usuario', function () {
    $competencia = CompetenciaModel::factory()->create();

    $this->putJson("/api/competencias/{$competencia->id}", datosCompetencia(['version' => 1]))->assertUnauthorized();

    actuarComoUsuario();
    $this->putJson("/api/competencias/{$competencia->id}", datosCompetencia(['version' => 1]))->assertForbidden();
});

it('devuelve 404 si no existe', function () {
    actuarComoAdmin();

    $this->putJson('/api/competencias/999', datosCompetencia(['version' => 1]))
        ->assertNotFound()->assertExactJson(['message' => 'La competencia no existe.']);
});

it('devuelve 409 con una versión desactualizada y no cambia nada', function () {
    $competencia = CompetenciaModel::factory()->create(['nombre' => 'Original']);
    actuarComoAdmin();
    $this->putJson("/api/competencias/{$competencia->id}", datosCompetencia(['nombre' => 'Primera edición', 'version' => 1]))->assertOk();

    $this->putJson("/api/competencias/{$competencia->id}", datosCompetencia(['nombre' => 'Segunda edición', 'version' => 1]))
        ->assertStatus(409)
        ->assertExactJson(['message' => 'La competencia cambió desde que la abriste. Recarga para ver la versión actual.']);

    expect($competencia->fresh()->nombre)->toBe('Primera edición');
});

it('exige la versión', function () {
    $competencia = CompetenciaModel::factory()->create();
    actuarComoAdmin();

    $this->putJson("/api/competencias/{$competencia->id}", datosCompetencia())
        ->assertUnprocessable()->assertJsonValidationErrors('version');
});

it('devuelve 422 con datos inválidos y no cambia la competencia', function () {
    $competencia = CompetenciaModel::factory()->create(['nombre' => 'Original']);
    actuarComoAdmin();

    $this->putJson("/api/competencias/{$competencia->id}", datosCompetencia(['nombre' => '', 'version' => 1]))
        ->assertUnprocessable()->assertJsonValidationErrors('nombre');

    $conExtra = datosCompetencia(['version' => 1]);
    $conExtra['grados']['E'] = 'extra';
    $this->putJson("/api/competencias/{$competencia->id}", $conExtra)->assertUnprocessable()->assertJsonValidationErrors('grados');

    expect($competencia->fresh()->nombre)->toBe('Original')->and($competencia->fresh()->version)->toBe(1);
});

it('rechaza el nombre de otra competencia pero permite conservar el propio', function () {
    CompetenciaModel::factory()->create(['nombre' => 'Primera']);
    $segunda = CompetenciaModel::factory()->create(['nombre' => 'Segunda']);
    actuarComoAdmin();

    $this->putJson("/api/competencias/{$segunda->id}", datosCompetencia(['nombre' => 'primera', 'version' => 1]))
        ->assertUnprocessable()->assertJsonValidationErrors('nombre');

    $this->putJson("/api/competencias/{$segunda->id}", datosCompetencia(['nombre' => 'SEGUNDA', 'version' => 1]))
        ->assertOk()->assertJsonPath('data.nombre', 'SEGUNDA');

    // Al renombrar se actualiza también la clave de unicidad: el nombre anterior queda libre.
    $this->postJson('/api/competencias', datosCompetencia(['nombre' => 'segunda']))->assertUnprocessable();
    $this->putJson("/api/competencias/{$segunda->id}", datosCompetencia(['nombre' => 'Tercera', 'version' => 2]))->assertOk();
    $this->postJson('/api/competencias', datosCompetencia(['nombre' => 'Segunda']))->assertCreated();
});
