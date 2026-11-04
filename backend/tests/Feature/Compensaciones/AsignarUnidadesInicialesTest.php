<?php

use App\Modules\Compensaciones\Infrastructure\Persistence\Eloquent\Models\AuditoriaCategoriaModel;
use App\Modules\Compensaciones\Infrastructure\Persistence\Eloquent\Models\CategoriaReconocimientoModel;
use App\Modules\Compensaciones\Presentation\Facades\CategoriasFacade;

it('asigna todas las unidades activas a las categorías sin unidades y no toca las demás', function () {
    $sinNada = CategoriaReconocimientoModel::factory()->create(['unidades_ids' => null]);
    $vacia = CategoriaReconocimientoModel::factory()->create(['unidades_ids' => []]);
    $conUnidades = CategoriaReconocimientoModel::factory()->create(['unidades_ids' => [2]]);

    $this->artisan('compensaciones:asignar-unidades-iniciales')->assertSuccessful();

    expect(CategoriaReconocimientoModel::query()->findOrFail($sinNada->id)->unidades_ids)->toBe([1, 2, 3])
        ->and(CategoriaReconocimientoModel::query()->findOrFail($vacia->id)->unidades_ids)->toBe([1, 2, 3])
        ->and(CategoriaReconocimientoModel::query()->findOrFail($conUnidades->id)->unidades_ids)->toBe([2]);
});

it('es idempotente: ejecutarlo dos veces no cambia nada ni duplica la auditoría', function () {
    $categoria = CategoriaReconocimientoModel::factory()->create(['unidades_ids' => null]);

    $this->artisan('compensaciones:asignar-unidades-iniciales')->assertSuccessful();
    $version = versionDeCategoria($categoria->id);

    $this->artisan('compensaciones:asignar-unidades-iniciales')->assertSuccessful();

    expect(versionDeCategoria($categoria->id))->toBe($version)
        ->and(AuditoriaCategoriaModel::query()->where('categoria_reconocimiento_id', $categoria->id)->where('campo', 'unidades')->count())->toBe(1);
});

it('registra en la auditoría la asignación sin actor', function () {
    $categoria = CategoriaReconocimientoModel::factory()->create(['unidades_ids' => null]);

    $this->artisan('compensaciones:asignar-unidades-iniciales')->assertSuccessful();

    $fila = AuditoriaCategoriaModel::query()->where('categoria_reconocimiento_id', $categoria->id)->where('campo', 'unidades')->sole();
    expect($fila->valor_anterior)->toBeNull()
        ->and($fila->valor_nuevo)->toBe('[1,2,3]')
        ->and($fila->user_id)->toBeNull();
});

it('una categoría sin unidades solo la ve el Gestor y no es seleccionable', function () {
    $categoria = CategoriaReconocimientoModel::factory()->create(['unidades_ids' => null]);

    actuarComoColaboradorDeUnidad(1);
    $this->getJson('/api/compensaciones/categorias')->assertOk()->assertJsonCount(0, 'data');

    actuarComoLiderDeEquipo(1);
    $this->getJson('/api/compensaciones/categorias')->assertOk()->assertJsonCount(0, 'data');

    actuarComoAdmin();
    $this->getJson('/api/compensaciones/categorias')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.sin_unidades_activas', true);

    expect(CategoriasFacade::seleccionablesParaUnidad(1))->toBe([]);
});

it('avisa y falla si no hay unidades activas', function () {
    CategoriaReconocimientoModel::factory()->create(['unidades_ids' => null]);
    foreach ([1, 2, 3] as $id) {
        escenarioCompensaciones()->unidades->unidades[$id]['estado'] = 'Inactivo';
    }

    $this->artisan('compensaciones:asignar-unidades-iniciales')->assertFailed();
});
