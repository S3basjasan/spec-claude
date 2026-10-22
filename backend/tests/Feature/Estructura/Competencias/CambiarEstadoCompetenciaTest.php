<?php

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaEstadoHistorialModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\PuestoCompetenciaModel;
use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Laravel\Sanctum\Sanctum;

it('desactiva y reactiva registrando el historial, la versión y el autor', function () {
    $competencia = CompetenciaModel::factory()->create();
    $admin = UserModel::factory()->admin()->create();
    Sanctum::actingAs($admin, ['admin']);

    $this->patchJson("/api/competencias/{$competencia->id}/estado", ['estado' => 'Inactivo'])
        ->assertOk()
        ->assertJsonPath('data.estado', 'Inactivo')
        ->assertJsonPath('data.version', 2);

    $this->patchJson("/api/competencias/{$competencia->id}/estado", ['estado' => 'Activo'])
        ->assertOk()
        ->assertJsonPath('data.estado', 'Activo')
        ->assertJsonPath('data.version', 3);

    $historial = CompetenciaEstadoHistorialModel::query()->where('competencia_id', $competencia->id)->orderBy('id')->get();
    expect($historial->map(fn ($h) => [$h->estado_anterior, $h->estado_nuevo, $h->user_id])->all())
        ->toBe([['Activo', 'Inactivo', $admin->id], ['Inactivo', 'Activo', $admin->id]])
        ->and($competencia->fresh()->updated_by)->toBe($admin->id);
});

it('rechaza con 422 un estado que ya es el pedido y no escribe historial', function () {
    $competencia = CompetenciaModel::factory()->create();
    actuarComoAdmin();

    $this->patchJson("/api/competencias/{$competencia->id}/estado", ['estado' => 'Activo'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.estado.0', 'La competencia ya está en ese estado.');

    expect(CompetenciaEstadoHistorialModel::query()->count())->toBe(0)
        ->and($competencia->fresh()->version)->toBe(1);
});

it('valida el valor del estado', function () {
    $competencia = CompetenciaModel::factory()->create();
    actuarComoAdmin();

    $this->patchJson("/api/competencias/{$competencia->id}/estado", ['estado' => 'Otro'])
        ->assertUnprocessable()->assertJsonValidationErrors('estado');
    $this->patchJson("/api/competencias/{$competencia->id}/estado", [])->assertUnprocessable();
});

it('no permite activar una competencia incompleta e indica lo que falta', function () {
    $incompleta = CompetenciaModel::factory()->incompleta()->inactiva()->create();
    $soloPreguntas = CompetenciaModel::factory()->sinPreguntas()->inactiva()->create();
    actuarComoAdmin();

    $this->patchJson("/api/competencias/{$incompleta->id}/estado", ['estado' => 'Activo'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.estado.0', 'No se puede activar: faltan los comportamientos y las preguntas.');
    $this->patchJson("/api/competencias/{$soloPreguntas->id}/estado", ['estado' => 'Activo'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.estado.0', 'No se puede activar: faltan las preguntas.');

    expect($incompleta->fresh()->estado)->toBe('Inactivo')
        ->and(CompetenciaEstadoHistorialModel::query()->count())->toBe(0);
});

it('permite activarla cuando se completa el contenido', function () {
    $incompleta = CompetenciaModel::factory()->incompleta()->inactiva()->create();
    actuarComoAdmin();

    $this->putJson("/api/competencias/{$incompleta->id}/comportamientos", ['version' => 1, 'comportamientos' => comportamientosNuevos()])->assertOk();
    $this->putJson("/api/competencias/{$incompleta->id}/preguntas", ['version' => 2, 'preguntas' => ['a', 'b', 'c', 'd']])->assertOk();

    $this->patchJson("/api/competencias/{$incompleta->id}/estado", ['estado' => 'Activo'])
        ->assertOk()->assertJsonPath('data.estado', 'Activo');
});

it('los demás usuarios dejan de ver la competencia desactivada y siguen pudiendo consultarla por id', function () {
    $competencia = CompetenciaModel::factory()->create(['nombre' => 'Retirable']);
    actuarComoAdmin();
    $this->patchJson("/api/competencias/{$competencia->id}/estado", ['estado' => 'Inactivo'])->assertOk();

    actuarComoUsuario();
    expect($this->getJson('/api/competencias')->json('data'))->toBe([]);
    $this->getJson("/api/competencias/{$competencia->id}")->assertOk()->assertJsonPath('data.estado', 'Inactivo');
});

it('desactivar no modifica las asignaciones existentes de los puestos', function () {
    $competencia = CompetenciaModel::factory()->create();
    $puesto = puestoDe();
    PuestoCompetenciaModel::query()->create(['puesto_id' => $puesto->id, 'competencia_id' => $competencia->id, 'grado_requerido' => 'C']);
    actuarComoAdmin();

    $this->patchJson("/api/competencias/{$competencia->id}/estado", ['estado' => 'Inactivo'])->assertOk();

    $asignacion = PuestoCompetenciaModel::query()->where('puesto_id', $puesto->id)->with('grado.valor')->first();
    expect($asignacion->grado_requerido)->toBe('C');
});

it('rechaza con 401, 403 y 404', function () {
    $competencia = CompetenciaModel::factory()->create();

    $this->patchJson("/api/competencias/{$competencia->id}/estado", ['estado' => 'Inactivo'])->assertUnauthorized();

    actuarComoUsuario();
    $this->patchJson("/api/competencias/{$competencia->id}/estado", ['estado' => 'Inactivo'])->assertForbidden();

    actuarComoAdmin();
    $this->patchJson('/api/competencias/99999/estado', ['estado' => 'Inactivo'])->assertNotFound();
});
