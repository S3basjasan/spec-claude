<?php

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaEstadoHistorialModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\RegistroComportamientosModel;
use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Laravel\Sanctum\Sanctum;

it('el estado del registro es siempre el de la competencia y cambia con ella', function () {
    actuarComoAdmin();
    $competencia = registroDe();

    $this->getJson("/api/comportamientos/{$competencia->id}")->assertJsonPath('data.estado', 'Activo');
    $this->patchJson("/api/competencias/{$competencia->id}/estado", ['estado' => 'Inactivo'])->assertOk();
    $this->getJson("/api/comportamientos/{$competencia->id}")->assertJsonPath('data.estado', 'Inactivo');
    $this->getJson('/api/comportamientos')->assertJsonPath('meta.total', 0);
    $this->getJson('/api/comportamientos?estado=Inactivo')->assertJsonPath('meta.total', 1);

    $this->patchJson("/api/competencias/{$competencia->id}/estado", ['estado' => 'Activo'])->assertOk();
    $this->getJson("/api/comportamientos/{$competencia->id}")->assertJsonPath('data.estado', 'Activo');
});

it('cambiar el estado de la competencia no toca el registro ni su historial propio', function () {
    actuarComoAdmin();
    $competencia = registroDe();
    $antes = RegistroComportamientosModel::query()->first()->only(['version', 'updated_by', 'updated_at']);

    $this->patchJson("/api/competencias/{$competencia->id}/estado", ['estado' => 'Inactivo'])->assertOk();

    expect(RegistroComportamientosModel::query()->first()->only(['version', 'updated_by', 'updated_at']))->toEqual($antes)
        ->and(CompetenciaEstadoHistorialModel::query()->where('competencia_id', $competencia->id)->count())->toBe(1)
        ->and(Schema::hasTable('competencia_comportamiento_historial'))->toBeFalse();
});

it('no existen rutas de activar, desactivar ni eliminar comportamientos', function () {
    $rutas = collect(Route::getRoutes()->getRoutes())->filter(fn ($r) => str_starts_with($r->uri(), 'api/comportamientos'));

    expect($rutas)->toHaveCount(5)
        ->and($rutas->flatMap(fn ($r) => $r->methods())->unique()->values()->sort()->values()->all())->toBe(['GET', 'HEAD', 'POST', 'PUT']);
});

it('audita quién creó y quién editó por última vez con dos administradores', function () {
    $ana = UserModel::factory()->admin()->create();
    $luis = UserModel::factory()->admin()->create();
    $competencia = CompetenciaModel::factory()->create();

    Sanctum::actingAs($ana, ['admin']);
    $this->postJson('/api/comportamientos', datosComportamientos($competencia->id))->assertCreated();
    Sanctum::actingAs($luis, ['admin']);
    $this->putJson("/api/comportamientos/{$competencia->id}", [
        ...datosComportamientos($competencia->id, ['comportamientos' => [...datosComportamientos($competencia->id)['comportamientos'], 'A' => ['Cambiado']]]),
        'version' => 1,
    ])->assertOk();

    $registro = RegistroComportamientosModel::query()->firstOrFail();
    expect($registro->created_by)->toBe($ana->id)
        ->and($registro->updated_by)->toBe($luis->id)
        ->and($registro->created_at)->not->toBeNull()
        ->and($registro->updated_at)->not->toBeNull();
});

it('un guardado sin cambios no altera la auditoría', function () {
    $ana = UserModel::factory()->admin()->create();
    $luis = UserModel::factory()->admin()->create();
    $competencia = CompetenciaModel::factory()->create();
    Sanctum::actingAs($ana, ['admin']);
    $this->postJson('/api/comportamientos', datosComportamientos($competencia->id))->assertCreated();
    $antes = RegistroComportamientosModel::query()->first();

    Sanctum::actingAs($luis, ['admin']);
    $this->travel(10)->minutes();
    $this->putJson("/api/comportamientos/{$competencia->id}", [...datosComportamientos($competencia->id), 'version' => 1])->assertOk();

    $despues = RegistroComportamientosModel::query()->first();
    expect($despues->updated_by)->toBeNull()
        ->and($despues->version)->toBe(1)
        ->and($despues->updated_at->equalTo($antes->updated_at))->toBeTrue();
});
