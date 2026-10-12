<?php

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaEstadoHistorialModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\PreguntaModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\RegistroPreguntasModel;
use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Laravel\Sanctum\Sanctum;

it('el estado del registro es siempre el de la competencia y cambia con ella', function () {
    actuarComoAdmin();
    $competencia = registroPreguntasDe();

    $this->getJson("/api/preguntas/{$competencia->id}")->assertJsonPath('data.estado', 'Activo');
    $this->patchJson("/api/competencias/{$competencia->id}/estado", ['estado' => 'Inactivo'])->assertOk();
    $this->getJson("/api/preguntas/{$competencia->id}")->assertJsonPath('data.estado', 'Inactivo');
    $this->getJson('/api/preguntas')->assertJsonPath('meta.total', 0);
    $this->getJson('/api/preguntas?estado=Inactivo')->assertJsonPath('meta.total', 1);

    $this->patchJson("/api/competencias/{$competencia->id}/estado", ['estado' => 'Activo'])->assertOk();
    $this->getJson("/api/preguntas/{$competencia->id}")->assertJsonPath('data.estado', 'Activo');
});

it('un Usuario deja de ver el registro al desactivar la competencia pero puede consultarlo por su identificador', function () {
    $competencia = registroPreguntasDe();
    actuarComoAdmin();
    $this->patchJson("/api/competencias/{$competencia->id}/estado", ['estado' => 'Inactivo'])->assertOk();

    actuarComoUsuario();
    $this->getJson('/api/preguntas')->assertJsonPath('meta.total', 0);
    $this->getJson("/api/preguntas/{$competencia->id}")->assertOk()->assertJsonPath('data.estado', 'Inactivo');
});

it('cambiar el estado de la competencia no toca el registro ni crea historial propio', function () {
    actuarComoAdmin();
    $competencia = registroPreguntasDe();
    $antes = RegistroPreguntasModel::query()->first()->only(['version', 'updated_by', 'updated_at']);

    $this->patchJson("/api/competencias/{$competencia->id}/estado", ['estado' => 'Inactivo'])->assertOk();
    $this->patchJson("/api/competencias/{$competencia->id}/estado", ['estado' => 'Activo'])->assertOk();

    expect(RegistroPreguntasModel::query()->first()->only(['version', 'updated_by', 'updated_at']))->toEqual($antes)
        ->and(PreguntaModel::query()->whereNotNull('updated_by')->count())->toBe(0)
        ->and(CompetenciaEstadoHistorialModel::query()->where('competencia_id', $competencia->id)->count())->toBe(2)
        ->and(Schema::hasTable('competencia_pregunta_historial'))->toBeFalse()
        ->and(Schema::hasColumn('competencia_pregunta_registros', 'estado'))->toBeFalse();
});

it('editar el registro de una competencia inactiva no cambia su estado', function () {
    actuarComoAdmin();
    $competencia = registroPreguntasDe(['estado' => 'Inactivo']);

    $this->putJson("/api/preguntas/{$competencia->id}", [...datosPreguntas($competencia->id), 'version' => 1])->assertOk();

    expect(CompetenciaModel::query()->find($competencia->id)->estado)->toBe('Inactivo');
});

it('no existen rutas de activar, desactivar ni eliminar preguntas', function () {
    $rutas = collect(Route::getRoutes()->getRoutes())->filter(fn ($r) => str_starts_with($r->uri(), 'api/preguntas'));

    expect($rutas)->toHaveCount(5)
        ->and($rutas->flatMap(fn ($r) => $r->methods())->unique()->values()->sort()->values()->all())->toBe(['GET', 'HEAD', 'POST', 'PUT']);

    actuarComoAdmin();
    $competencia = registroPreguntasDe();
    $this->deleteJson("/api/preguntas/{$competencia->id}")->assertStatus(405);
    $this->patchJson("/api/preguntas/{$competencia->id}")->assertStatus(405);
    expect(RegistroPreguntasModel::query()->count())->toBe(1);
});

it('audita quién creó y quién editó por última vez con dos administradores', function () {
    $ana = UserModel::factory()->admin()->create();
    $luis = UserModel::factory()->admin()->create();
    $competencia = CompetenciaModel::factory()->create();

    Sanctum::actingAs($ana, ['admin']);
    $this->postJson('/api/preguntas', datosPreguntas($competencia->id))->assertCreated();
    Sanctum::actingAs($luis, ['admin']);
    $this->putJson("/api/preguntas/{$competencia->id}", [
        ...datosPreguntas($competencia->id, ['preguntas' => ['Cambiada', 'B', 'C', 'D']]),
        'version' => 1,
    ])->assertOk();

    $registro = RegistroPreguntasModel::query()->firstOrFail();
    $filas = PreguntaModel::query()->orderBy('orden')->get();
    expect($registro->created_by)->toBe($ana->id)
        ->and($registro->updated_by)->toBe($luis->id)
        ->and($registro->version)->toBe(2)
        ->and($registro->created_at)->not->toBeNull()
        ->and($registro->updated_at)->not->toBeNull()
        ->and($filas->pluck('created_by')->unique()->all())->toBe([$ana->id])
        ->and($filas[0]->updated_by)->toBe($luis->id);
});

it('un guardado sin cambios no altera la auditoría', function () {
    $ana = UserModel::factory()->admin()->create();
    $luis = UserModel::factory()->admin()->create();
    $competencia = CompetenciaModel::factory()->create();
    Sanctum::actingAs($ana, ['admin']);
    $this->postJson('/api/preguntas', datosPreguntas($competencia->id))->assertCreated();
    $antes = RegistroPreguntasModel::query()->first();

    Sanctum::actingAs($luis, ['admin']);
    $this->travel(10)->minutes();
    $this->putJson("/api/preguntas/{$competencia->id}", [...datosPreguntas($competencia->id), 'version' => 1])->assertOk();

    $despues = RegistroPreguntasModel::query()->first();
    expect($despues->updated_by)->toBeNull()
        ->and($despues->version)->toBe(1)
        ->and($despues->updated_at->equalTo($antes->updated_at))->toBeTrue();
});
