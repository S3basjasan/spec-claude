<?php

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\PreguntaModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\RegistroPreguntasModel;
use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Laravel\Sanctum\Sanctum;

/**
 * @param  array<string, mixed>  $cambios
 * @return array<string, mixed>
 */
function cuerpoPreguntasActualizado(int $competenciaId, array $cambios = []): array
{
    return [...datosPreguntas($competenciaId), 'version' => 1, ...$cambios];
}

it('reemplaza las preguntas, sube la versión y registra quién editó', function () {
    $editor = UserModel::factory()->admin()->create();
    Sanctum::actingAs($editor, ['admin']);
    $competencia = registroPreguntasDe();

    $this->putJson("/api/preguntas/{$competencia->id}", cuerpoPreguntasActualizado($competencia->id))
        ->assertOk()
        ->assertJsonPath('data.version', 2)
        ->assertJsonPath('data.preguntas.0', 'Cuéntame de una meta que lograste.')
        ->assertJsonCount(4, 'data.preguntas');

    $registro = RegistroPreguntasModel::query()->first();
    expect($registro->updated_by)->toBe($editor->id)->and(PreguntaModel::query()->count())->toBe(4);
});

it('permite editar con la competencia inactiva', function () {
    actuarComoAdmin();
    $competencia = registroPreguntasDe(['estado' => 'Inactivo']);

    $this->putJson("/api/preguntas/{$competencia->id}", cuerpoPreguntasActualizado($competencia->id))
        ->assertOk()->assertJsonPath('data.estado', 'Inactivo');
});

it('guardar sin cambios reales responde 200 sin cambiar la versión', function () {
    actuarComoAdmin();
    $competencia = registroPreguntasDe();
    $actuales = $this->getJson("/api/preguntas/{$competencia->id}")->json('data.preguntas');

    $this->putJson("/api/preguntas/{$competencia->id}", ['preguntas' => $actuales, 'version' => 1])
        ->assertOk()->assertJsonPath('data.version', 1);
    expect(RegistroPreguntasModel::query()->first()->updated_by)->toBeNull();
});

it('devuelve 409 con versión desactualizada y no modifica nada', function () {
    actuarComoAdmin();
    $competencia = registroPreguntasDe();
    $this->putJson("/api/preguntas/{$competencia->id}", cuerpoPreguntasActualizado($competencia->id))->assertOk();

    $this->putJson("/api/preguntas/{$competencia->id}", cuerpoPreguntasActualizado($competencia->id, ['preguntas' => ['a', 'b', 'c', 'd']]))
        ->assertStatus(409)
        ->assertExactJson(['message' => 'El registro cambió desde que lo abriste. Recarga para ver la versión actual.']);

    expect(RegistroPreguntasModel::query()->first()->version)->toBe(2)
        ->and(PreguntaModel::query()->where('pregunta', 'a')->exists())->toBeFalse();
});

it('devuelve 422 si competencia_id difiere de la ruta, si falta la versión o hay datos inválidos', function () {
    actuarComoAdmin();
    $competencia = registroPreguntasDe();
    $otra = registroPreguntasDe();

    $this->putJson("/api/preguntas/{$competencia->id}", cuerpoPreguntasActualizado($otra->id))
        ->assertUnprocessable()->assertJsonValidationErrors(['competencia_id']);
    $sinVersion = cuerpoPreguntasActualizado($competencia->id);
    unset($sinVersion['version']);
    $this->putJson("/api/preguntas/{$competencia->id}", $sinVersion)
        ->assertUnprocessable()->assertJsonValidationErrors(['version']);
    $this->putJson("/api/preguntas/{$competencia->id}", cuerpoPreguntasActualizado($competencia->id, ['preguntas' => ['a', 'b', 'c']]))
        ->assertUnprocessable()->assertJsonValidationErrors(['preguntas']);
    $this->putJson("/api/preguntas/{$competencia->id}", cuerpoPreguntasActualizado($competencia->id, ['preguntas' => ['a', 'A ', 'c', 'd']]))
        ->assertUnprocessable()->assertJsonValidationErrors(['preguntas.1']);
});

it('devuelve 404, 401 y 403 según corresponda', function () {
    $competencia = registroPreguntasDe();
    $this->putJson("/api/preguntas/{$competencia->id}", cuerpoPreguntasActualizado($competencia->id))->assertUnauthorized();

    actuarComoUsuario();
    $this->putJson("/api/preguntas/{$competencia->id}", cuerpoPreguntasActualizado($competencia->id))->assertForbidden();

    actuarComoAdmin();
    $this->putJson('/api/preguntas/999', cuerpoPreguntasActualizado(999))
        ->assertNotFound()->assertExactJson(['message' => 'El registro de preguntas no existe.']);
});
