<?php

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaEstadoHistorialModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;
use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\QueryException;
use Laravel\Sanctum\Sanctum;

it('audita el ciclo completo con dos administradores distintos', function () {
    $ana = UserModel::factory()->admin()->create();
    $luis = UserModel::factory()->admin()->create();

    Sanctum::actingAs($ana, ['admin']);
    $id = $this->postJson('/api/competencias', datosCompetencia())->assertCreated()->json('data.id');

    Sanctum::actingAs($luis, ['admin']);
    $this->putJson("/api/competencias/$id", datosCompetencia(['descripcion' => 'Corregida.', 'version' => 1]))->assertOk();
    $this->patchJson("/api/competencias/$id/estado", ['estado' => 'Inactivo'])->assertOk();

    Sanctum::actingAs($ana, ['admin']);
    $this->patchJson("/api/competencias/$id/estado", ['estado' => 'Activo'])->assertOk();

    $competencia = CompetenciaModel::query()->findOrFail($id);
    expect($competencia->created_by)->toBe($ana->id)
        ->and($competencia->updated_by)->toBe($luis->id)
        ->and($competencia->created_at)->not->toBeNull()
        ->and($competencia->updated_at)->not->toBeNull();

    $historial = CompetenciaEstadoHistorialModel::query()->where('competencia_id', $id)->orderBy('id')->get();
    expect($historial)->toHaveCount(2)
        ->and([$historial[0]->user_id, $historial[0]->estado_anterior, $historial[0]->estado_nuevo])->toBe([$luis->id, 'Activo', 'Inactivo'])
        ->and([$historial[1]->user_id, $historial[1]->estado_anterior, $historial[1]->estado_nuevo])->toBe([$ana->id, 'Inactivo', 'Activo']);
});

it('la creación no genera registro de historial y los cambios previos se conservan', function () {
    actuarComoAdmin();
    $id = $this->postJson('/api/competencias', datosCompetencia())->assertCreated()->json('data.id');
    expect(CompetenciaEstadoHistorialModel::query()->count())->toBe(0);

    $this->patchJson("/api/competencias/$id/estado", ['estado' => 'Inactivo'])->assertOk();
    $primero = CompetenciaEstadoHistorialModel::query()->sole();

    $this->patchJson("/api/competencias/$id/estado", ['estado' => 'Activo'])->assertOk();

    expect(CompetenciaEstadoHistorialModel::query()->count())->toBe(2)
        ->and(CompetenciaEstadoHistorialModel::query()->find($primero->id)->estado_nuevo)->toBe('Inactivo');
});

it('un cambio repetido a un mismo estado deja una sola fila', function () {
    $competencia = CompetenciaModel::factory()->create();
    actuarComoAdmin();

    $this->patchJson("/api/competencias/{$competencia->id}/estado", ['estado' => 'Inactivo'])->assertOk();
    $this->patchJson("/api/competencias/{$competencia->id}/estado", ['estado' => 'Inactivo'])->assertUnprocessable();

    expect(CompetenciaEstadoHistorialModel::query()->count())->toBe(1);
});

it('el historial no se puede borrar aunque se borre la competencia (clave foránea sin cascada)', function () {
    $competencia = CompetenciaModel::factory()->create();
    actuarComoAdmin();
    $this->patchJson("/api/competencias/{$competencia->id}/estado", ['estado' => 'Inactivo'])->assertOk();

    expect(fn () => DB::table('competencias')->where('id', $competencia->id)->delete())
        ->toThrow(QueryException::class)
        ->and(CompetenciaEstadoHistorialModel::query()->count())->toBe(1);
});
