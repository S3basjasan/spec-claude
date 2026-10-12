<?php

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaEstadoHistorialModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;
use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Laravel\Sanctum\Sanctum;

it('desactiva y vuelve a activar una competencia', function () {
    $competencia = CompetenciaModel::factory()->create();
    actuarComoAdmin();

    $this->patchJson("/api/competencias/{$competencia->id}/estado", ['estado' => 'Inactivo'])
        ->assertOk()
        ->assertJsonPath('data.estado', 'Inactivo')
        ->assertJsonPath('data.codigo', 'COMP-0001')
        ->assertJsonCount(4, 'data.grados');

    $this->patchJson("/api/competencias/{$competencia->id}/estado", ['estado' => 'Activo'])
        ->assertOk()->assertJsonPath('data.estado', 'Activo');
});

it('rechaza con 401 sin sesión y con 403 al Usuario sin cambiar nada', function () {
    $competencia = CompetenciaModel::factory()->create();

    $this->patchJson("/api/competencias/{$competencia->id}/estado", ['estado' => 'Inactivo'])->assertUnauthorized();

    actuarComoUsuario();
    $this->patchJson("/api/competencias/{$competencia->id}/estado", ['estado' => 'Inactivo'])->assertForbidden();

    expect($competencia->fresh()->estado)->toBe('Activo')
        ->and(CompetenciaEstadoHistorialModel::query()->count())->toBe(0);
});

it('devuelve 404 si no existe', function () {
    actuarComoAdmin();

    $this->patchJson('/api/competencias/999/estado', ['estado' => 'Inactivo'])
        ->assertNotFound()->assertExactJson(['message' => 'La competencia no existe.']);
});

it('devuelve 422 con un estado ausente o inválido', function () {
    $competencia = CompetenciaModel::factory()->create();
    actuarComoAdmin();

    $this->patchJson("/api/competencias/{$competencia->id}/estado", [])
        ->assertUnprocessable()->assertJsonValidationErrors('estado');
    $this->patchJson("/api/competencias/{$competencia->id}/estado", ['estado' => 'Borrado'])
        ->assertUnprocessable()->assertJsonValidationErrors('estado');
    $this->patchJson("/api/competencias/{$competencia->id}/estado", ['estado' => 'inactivo'])
        ->assertUnprocessable()->assertJsonValidationErrors('estado');
});

it('devuelve 422 si ya está en el estado pedido y no escribe historial', function () {
    $competencia = CompetenciaModel::factory()->create();
    actuarComoAdmin();

    $this->patchJson("/api/competencias/{$competencia->id}/estado", ['estado' => 'Activo'])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'La competencia ya está en ese estado.')
        ->assertJsonValidationErrors('estado');

    $this->patchJson("/api/competencias/{$competencia->id}/estado", ['estado' => 'Inactivo'])->assertOk();
    $this->patchJson("/api/competencias/{$competencia->id}/estado", ['estado' => 'Inactivo'])->assertUnprocessable();

    expect(CompetenciaEstadoHistorialModel::query()->count())->toBe(1);
});

it('no cambia la versión ni quién editó por último', function () {
    $competencia = CompetenciaModel::factory()->create(['version' => 4, 'updated_by' => 9]);
    actuarComoAdmin();

    $this->patchJson("/api/competencias/{$competencia->id}/estado", ['estado' => 'Inactivo'])
        ->assertOk()->assertJsonPath('data.version', 4);

    expect($competencia->fresh()->version)->toBe(4)->and($competencia->fresh()->updated_by)->toBe(9);
});

it('al desactivar, el Usuario deja de verla en el listado pero puede consultarla', function () {
    $competencia = CompetenciaModel::factory()->create(['nombre' => 'Saliente']);
    actuarComoAdmin();
    $this->patchJson("/api/competencias/{$competencia->id}/estado", ['estado' => 'Inactivo'])->assertOk();

    actuarComoUsuario();
    $this->getJson('/api/competencias')->assertOk()->assertJsonPath('meta.total', 0);
    $this->getJson("/api/competencias/{$competencia->id}")->assertOk()->assertJsonPath('data.estado', 'Inactivo');

    actuarComoAdmin();
    $this->getJson('/api/competencias')->assertOk()->assertJsonPath('meta.total', 0);
    $this->getJson('/api/competencias?estado=Inactivo')->assertOk()->assertJsonPath('meta.total', 1);
});

it('un cambio de estado no provoca conflicto en una edición abierta antes', function () {
    $competencia = CompetenciaModel::factory()->create(['nombre' => 'Original']);
    actuarComoAdmin();
    $this->patchJson("/api/competencias/{$competencia->id}/estado", ['estado' => 'Inactivo'])->assertOk();

    $this->putJson("/api/competencias/{$competencia->id}", datosCompetencia(['nombre' => 'Editada', 'version' => 1]))
        ->assertOk()
        ->assertJsonPath('data.estado', 'Inactivo')
        ->assertJsonPath('data.nombre', 'Editada');
});

it('registra el historial con usuario, estado anterior y nuevo', function () {
    $competencia = CompetenciaModel::factory()->create();
    $admin = UserModel::factory()->admin()->create();
    Sanctum::actingAs($admin, ['admin']);

    $this->patchJson("/api/competencias/{$competencia->id}/estado", ['estado' => 'Inactivo'])->assertOk();

    $registro = CompetenciaEstadoHistorialModel::query()->sole();
    expect($registro->competencia_id)->toBe($competencia->id)
        ->and($registro->estado_anterior)->toBe('Activo')
        ->and($registro->estado_nuevo)->toBe('Inactivo')
        ->and($registro->user_id)->toBe($admin->id)
        ->and($registro->created_at)->not->toBeNull();
});
