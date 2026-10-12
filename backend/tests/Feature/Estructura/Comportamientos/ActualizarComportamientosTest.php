<?php

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\ComportamientoModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\RegistroComportamientosModel;
use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Laravel\Sanctum\Sanctum;

/**
 * @param  array<string, mixed>  $cambios
 * @return array<string, mixed>
 */
function cuerpoActualizado(int $competenciaId, array $cambios = []): array
{
    return [...datosComportamientos($competenciaId), 'version' => 1, ...$cambios];
}

it('reemplaza los comportamientos, sube la versión y registra quién editó', function () {
    $editor = UserModel::factory()->admin()->create();
    Sanctum::actingAs($editor, ['admin']);
    $competencia = registroDe();

    $this->putJson("/api/comportamientos/{$competencia->id}", cuerpoActualizado($competencia->id))
        ->assertOk()
        ->assertJsonPath('data.version', 2)
        ->assertJsonPath('data.comportamientos.A', ['Lidera con el ejemplo.', 'Inspira a otros.'])
        ->assertJsonPath('data.comportamientos.B', ['Coordina al equipo.']);

    $registro = RegistroComportamientosModel::query()->first();
    expect($registro->updated_by)->toBe($editor->id)->and(ComportamientoModel::query()->count())->toBe(6);
});

it('permite editar con la competencia inactiva', function () {
    actuarComoAdmin();
    $competencia = registroDe(['estado' => 'Inactivo']);

    $this->putJson("/api/comportamientos/{$competencia->id}", cuerpoActualizado($competencia->id))
        ->assertOk()->assertJsonPath('data.estado', 'Inactivo');
});

it('guardar sin cambios reales responde 200 sin cambiar la versión', function () {
    actuarComoAdmin();
    $competencia = registroDe();
    $actual = $this->getJson("/api/comportamientos/{$competencia->id}")->json('data.comportamientos');

    $this->putJson("/api/comportamientos/{$competencia->id}", ['comportamientos' => $actual, 'version' => 1])
        ->assertOk()->assertJsonPath('data.version', 1);
    expect(RegistroComportamientosModel::query()->first()->updated_by)->toBeNull();
});

it('devuelve 409 con versión desactualizada y no modifica nada', function () {
    actuarComoAdmin();
    $competencia = registroDe();
    $this->putJson("/api/comportamientos/{$competencia->id}", cuerpoActualizado($competencia->id))->assertOk();

    $this->putJson("/api/comportamientos/{$competencia->id}", cuerpoActualizado($competencia->id, [
        'comportamientos' => [...datosComportamientos($competencia->id)['comportamientos'], 'A' => ['Otro texto']],
    ]))->assertStatus(409)
        ->assertExactJson(['message' => 'El registro cambió desde que lo abriste. Recarga para ver la versión actual.']);

    expect(RegistroComportamientosModel::query()->first()->version)->toBe(2);
});

it('devuelve 422 si competencia_id difiere de la ruta, si falta la versión o hay datos inválidos', function () {
    actuarComoAdmin();
    $competencia = registroDe();
    $otra = registroDe();

    $this->putJson("/api/comportamientos/{$competencia->id}", cuerpoActualizado($otra->id))
        ->assertUnprocessable()->assertJsonValidationErrors(['competencia_id']);
    $sinVersion = cuerpoActualizado($competencia->id);
    unset($sinVersion['version']);
    $this->putJson("/api/comportamientos/{$competencia->id}", $sinVersion)
        ->assertUnprocessable()->assertJsonValidationErrors(['version']);
    $this->putJson("/api/comportamientos/{$competencia->id}", cuerpoActualizado($competencia->id, [
        'comportamientos' => [...datosComportamientos($competencia->id)['comportamientos'], 'A' => []],
    ]))->assertUnprocessable()->assertJsonValidationErrors(['comportamientos.A']);
});

it('devuelve 404, 401 y 403 según corresponda', function () {
    $competencia = registroDe();
    $this->putJson("/api/comportamientos/{$competencia->id}", cuerpoActualizado($competencia->id))->assertUnauthorized();

    actuarComoUsuario();
    $this->putJson("/api/comportamientos/{$competencia->id}", cuerpoActualizado($competencia->id))->assertForbidden();

    actuarComoAdmin();
    $this->putJson('/api/comportamientos/999', cuerpoActualizado(999))
        ->assertNotFound()->assertExactJson(['message' => 'El registro de comportamientos no existe.']);
});
