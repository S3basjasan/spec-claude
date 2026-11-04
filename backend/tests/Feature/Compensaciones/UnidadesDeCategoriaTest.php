<?php

use App\Modules\Compensaciones\Infrastructure\Persistence\Eloquent\Models\AuditoriaCategoriaModel;
use App\Modules\Compensaciones\Infrastructure\Persistence\Eloquent\Models\CategoriaReconocimientoModel;

beforeEach(fn () => actuarComoAdmin());

it('crea la categoría con las unidades elegidas, ordenadas', function () {
    $respuesta = $this->postJson('/api/compensaciones/categorias', datosCategoria(null, ['unidades_ids' => [3, 1]]))
        ->assertCreated()
        ->assertJsonPath('data.unidades.*.id', [1, 3])
        ->assertJsonPath('data.unidades.*.nombre', ['Operaciones', 'Finanzas'])
        ->assertJsonPath('data.aplica_a_todas', false);

    expect(CategoriaReconocimientoModel::query()->findOrFail($respuesta->json('data.id'))->unidades_ids)->toBe([1, 3]);
});

it('rechaza con 422 una categoría sin unidades', function (array $cambios) {
    $cuerpo = datosCategoria();
    unset($cuerpo['unidades_ids']);

    $this->postJson('/api/compensaciones/categorias', array_replace($cuerpo, $cambios))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Elige al menos una unidad.');

    expect(CategoriaReconocimientoModel::query()->count())->toBe(0);
})->with([
    'ausente' => [[]],
    'vacía' => [['unidades_ids' => []]],
    'con basura' => [['unidades_ids' => ['abc']]],
]);

it('rechaza con 422 una unidad inactiva o inexistente', function (int $unidadId) {
    $this->postJson('/api/compensaciones/categorias', datosCategoria(null, ['unidades_ids' => [1, $unidadId]]))
        ->assertUnprocessable()
        ->assertJsonPath('errors.unidades_ids.0', 'La unidad seleccionada ya no está activa.');

    expect(CategoriaReconocimientoModel::query()->count())->toBe(0);
})->with(['inactiva' => 4, 'inexistente' => 99]);

it('«Todas las unidades» guarda las activas del momento y no agrega después las nuevas', function () {
    $cuerpo = datosCategoria();
    unset($cuerpo['unidades_ids']);
    $cuerpo['todas_las_unidades'] = true;

    $id = $this->postJson('/api/compensaciones/categorias', $cuerpo)
        ->assertCreated()
        ->assertJsonPath('data.aplica_a_todas', true)
        ->json('data.id');

    escenarioCompensaciones()->unidades->agregar(5, 'Nueva unidad');

    expect(CategoriaReconocimientoModel::query()->findOrFail($id)->unidades_ids)->toBe([1, 2, 3]);

    $this->getJson("/api/compensaciones/categorias/{$id}")->assertOk()->assertJsonPath('data.aplica_a_todas', false);
});

it('si llegan ambos campos prevalece «Todas las unidades»', function () {
    $id = $this->postJson('/api/compensaciones/categorias', datosCategoria(null, ['unidades_ids' => [1], 'todas_las_unidades' => true]))
        ->assertCreated()
        ->json('data.id');

    expect(CategoriaReconocimientoModel::query()->findOrFail($id)->unidades_ids)->toBe([1, 2, 3]);
});

it('registra las unidades en la auditoría inicial', function () {
    $id = $this->postJson('/api/compensaciones/categorias', datosCategoria(null, ['unidades_ids' => [2, 1]]))
        ->assertCreated()
        ->json('data.id');

    $fila = AuditoriaCategoriaModel::query()->where('categoria_reconocimiento_id', $id)->where('campo', 'unidades')->sole();
    expect($fila->valor_anterior)->toBeNull()->and($fila->valor_nuevo)->toBe('[1,2]');
});

it('al editar reemplaza la lista y audita el cambio de unidades', function () {
    $categoria = CategoriaReconocimientoModel::factory()->create(['unidades_ids' => [1]]);

    $this->putJson("/api/compensaciones/categorias/{$categoria->id}", cuerpoDeCategoria($categoria, ['unidades_ids' => [2, 1]]))
        ->assertOk()
        ->assertJsonPath('data.unidades.*.id', [1, 2]);

    $fila = AuditoriaCategoriaModel::query()->where('categoria_reconocimiento_id', $categoria->id)->where('campo', 'unidades')->sole();
    expect($fila->valor_anterior)->toBe('[1]')
        ->and($fila->valor_nuevo)->toBe('[1,2]')
        ->and(CategoriaReconocimientoModel::query()->findOrFail($categoria->id)->unidades_ids)->toBe([1, 2]);
});

it('guardar sin cambiar las unidades no agrega fila de auditoría de unidades', function () {
    $categoria = CategoriaReconocimientoModel::factory()->create(['unidades_ids' => [1, 2]]);

    $this->putJson("/api/compensaciones/categorias/{$categoria->id}", cuerpoDeCategoria($categoria, ['unidades_ids' => [2, 1], 'puntuacion_base' => 55]))->assertOk();

    $campos = AuditoriaCategoriaModel::query()->where('categoria_reconocimiento_id', $categoria->id)->pluck('campo')->all();
    expect($campos)->toContain('puntuacion_base')->not->toContain('unidades');
});

it('al editar rechaza dejar la categoría sin unidades y no cambia nada', function () {
    $categoria = CategoriaReconocimientoModel::factory()->create(['unidades_ids' => [1]]);

    $this->putJson("/api/compensaciones/categorias/{$categoria->id}", cuerpoDeCategoria($categoria, ['unidades_ids' => []]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['unidades_ids']);

    expect(CategoriaReconocimientoModel::query()->findOrFail($categoria->id)->unidades_ids)->toBe([1]);
});

it('responde 409 por versión antes que 422 por unidades', function () {
    $categoria = CategoriaReconocimientoModel::factory()->create(['unidades_ids' => [1]]);
    $cuerpo = cuerpoDeCategoria($categoria, ['unidades_ids' => [4]]);

    $this->putJson("/api/compensaciones/categorias/{$categoria->id}", cuerpoDeCategoria($categoria, ['nombre' => 'Cambiada por otro']))->assertOk();

    $this->putJson("/api/compensaciones/categorias/{$categoria->id}", $cuerpo)->assertConflict();
});

it('conserva la asignación al desactivar', function () {
    $categoria = CategoriaReconocimientoModel::factory()->create(['unidades_ids' => [2, 3]]);

    $this->patchJson("/api/compensaciones/categorias/{$categoria->id}/estado", ['estado' => 'Inactivo', 'updated_at' => versionDeCategoria($categoria->id)])->assertOk();

    expect(CategoriaReconocimientoModel::query()->findOrFail($categoria->id)->unidades_ids)->toBe([2, 3]);
});
