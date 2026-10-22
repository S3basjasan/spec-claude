<?php

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaEscalaHistorialModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaEstadoHistorialModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;
use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Laravel\Sanctum\Sanctum;

it('registra quién y cuándo creó la competencia y la devuelve con el nombre del usuario', function () {
    $admin = UserModel::factory()->admin()->create(['name' => 'Marta Ríos']);
    Sanctum::actingAs($admin, ['admin']);

    $this->postJson('/api/competencias', datosCompetencia())
        ->assertCreated()
        ->assertJsonPath('data.auditoria.created_by', 'Marta Ríos')
        ->assertJsonPath('data.auditoria.updated_by', null);

    $competencia = CompetenciaModel::query()->first();
    expect($competencia->created_by)->toBe($admin->id)->and($competencia->created_at)->not->toBeNull();
});

it('registra quién modificó por última vez al editar cualquier sección, la escala o el estado', function () {
    $competencia = CompetenciaModel::factory()->create();
    $primero = UserModel::factory()->admin()->create(['name' => 'Primero']);
    $segundo = UserModel::factory()->admin()->create(['name' => 'Segundo']);
    $tercero = UserModel::factory()->admin()->create(['name' => 'Tercero']);

    Sanctum::actingAs($primero, ['admin']);
    $this->putJson("/api/competencias/{$competencia->id}/preguntas", ['version' => 1, 'preguntas' => ['a', 'b', 'c', 'd']])->assertOk();
    expect($competencia->fresh()->updated_by)->toBe($primero->id);

    Sanctum::actingAs($segundo, ['admin']);
    $this->patchJson("/api/competencias/{$competencia->id}/escala", ['tipo_grado_id' => escalaId('Números')])
        ->assertOk()->assertJsonPath('data.auditoria.updated_by', 'Segundo');

    Sanctum::actingAs($tercero, ['admin']);
    $this->patchJson("/api/competencias/{$competencia->id}/estado", ['estado' => 'Inactivo'])
        ->assertOk()->assertJsonPath('data.auditoria.updated_by', 'Tercero');
});

it('guarda el valor anterior, el nuevo, el usuario y la fecha de cada cambio de estado y de escala', function () {
    $competencia = CompetenciaModel::factory()->create();
    $admin = UserModel::factory()->admin()->create();
    Sanctum::actingAs($admin, ['admin']);

    $this->patchJson("/api/competencias/{$competencia->id}/estado", ['estado' => 'Inactivo'])->assertOk();
    $this->patchJson("/api/competencias/{$competencia->id}/escala", ['tipo_grado_id' => escalaId('Categorías')])->assertOk();

    $estado = CompetenciaEstadoHistorialModel::query()->first();
    $escala = CompetenciaEscalaHistorialModel::query()->first();
    expect([$estado->estado_anterior, $estado->estado_nuevo, $estado->user_id])->toBe(['Activo', 'Inactivo', $admin->id])
        ->and($estado->created_at)->not->toBeNull()
        ->and([$escala->escala_anterior, $escala->escala_nueva, $escala->user_id])->toBe(['Letras', 'Categorías', $admin->id])
        ->and($escala->created_at)->not->toBeNull();
});

it('los historiales son de solo inserción', function () {
    $competencia = CompetenciaModel::factory()->create();
    actuarComoAdmin();
    $this->patchJson("/api/competencias/{$competencia->id}/estado", ['estado' => 'Inactivo'])->assertOk();
    $this->patchJson("/api/competencias/{$competencia->id}/escala", ['tipo_grado_id' => escalaId('Números')])->assertOk();

    expect(fn () => CompetenciaEstadoHistorialModel::query()->first()->delete())->toThrow(LogicException::class)
        ->and(fn () => CompetenciaEscalaHistorialModel::query()->first()->update(['escala_nueva' => 'X']))->toThrow(LogicException::class);
});

it('solo el administrador recibe la auditoría', function () {
    $competencia = CompetenciaModel::factory()->create();

    actuarComoUsuario();
    $this->getJson("/api/competencias/{$competencia->id}")->assertOk()->assertJsonMissingPath('data.auditoria');

    actuarComoAdmin();
    $this->getJson("/api/competencias/{$competencia->id}")->assertOk()->assertJsonStructure(['data' => ['auditoria' => ['created_by', 'created_at', 'updated_by', 'updated_at']]]);
});
