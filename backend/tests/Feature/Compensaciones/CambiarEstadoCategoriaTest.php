<?php

use App\Modules\Compensaciones\Infrastructure\Persistence\Eloquent\Models\AuditoriaCategoriaModel;
use App\Modules\Compensaciones\Infrastructure\Persistence\Eloquent\Models\CategoriaReconocimientoModel;

it('rechaza con 401 sin sesión, 403 a un Usuario y 404 si no existe', function () {
    $categoria = CategoriaReconocimientoModel::factory()->create();
    $cuerpo = ['estado' => 'Inactivo', 'updated_at' => versionDeCategoria($categoria->id)];

    $this->patchJson("/api/compensaciones/categorias/{$categoria->id}/estado", $cuerpo)->assertUnauthorized();

    actuarComoUsuario();
    $this->patchJson("/api/compensaciones/categorias/{$categoria->id}/estado", $cuerpo)->assertForbidden();

    actuarComoAdmin();
    $this->patchJson('/api/compensaciones/categorias/9999/estado', $cuerpo)->assertNotFound();
    expect($categoria->fresh()->estado)->toBe('Activo');
});

it('desactiva y vuelve a activar registrando el cambio de estado', function () {
    $categoria = CategoriaReconocimientoModel::factory()->create();
    actuarComoAdmin();

    $this->patchJson("/api/compensaciones/categorias/{$categoria->id}/estado", [
        'estado' => 'Inactivo',
        'updated_at' => versionDeCategoria($categoria->id),
    ])
        ->assertOk()
        ->assertJsonPath('data.estado', 'Inactivo')
        ->assertJsonPath('data.seleccionable', false);

    $this->patchJson("/api/compensaciones/categorias/{$categoria->id}/estado", [
        'estado' => 'Activo',
        'updated_at' => versionDeCategoria($categoria->id),
    ])
        ->assertOk()
        ->assertJsonPath('data.estado', 'Activo')
        ->assertJsonPath('data.seleccionable', true);

    $filas = AuditoriaCategoriaModel::query()->where('categoria_reconocimiento_id', $categoria->id)->orderBy('id')->get();
    expect($filas->pluck('campo')->all())->toBe(['estado', 'estado'])
        ->and($filas->map(fn ($f) => [$f->valor_anterior, $f->valor_nuevo])->all())->toBe([['Activo', 'Inactivo'], ['Inactivo', 'Activo']]);
});

it('responde 200 sin cambios ni auditoría si ya tiene el estado pedido con la versión vigente', function () {
    $categoria = CategoriaReconocimientoModel::factory()->create();
    $version = versionDeCategoria($categoria->id);
    actuarComoAdmin();

    $this->patchJson("/api/compensaciones/categorias/{$categoria->id}/estado", ['estado' => 'Activo', 'updated_at' => $version])
        ->assertOk()
        ->assertJsonPath('data.estado', 'Activo')
        ->assertJsonPath('data.updated_at', $version);

    expect(versionDeCategoria($categoria->id))->toBe($version);
    $this->assertDatabaseCount('auditoria_categorias_reconocimiento', 0);
});

it('rechaza con 409 si la versión está desactualizada, incluso si ya tiene el estado pedido', function () {
    $categoria = CategoriaReconocimientoModel::factory()->create();
    $versionVieja = versionDeCategoria($categoria->id);
    actuarComoAdmin();

    $this->patchJson("/api/compensaciones/categorias/{$categoria->id}/estado", ['estado' => 'Inactivo', 'updated_at' => $versionVieja])->assertOk();

    $this->patchJson("/api/compensaciones/categorias/{$categoria->id}/estado", ['estado' => 'Inactivo', 'updated_at' => $versionVieja])
        ->assertStatus(409)
        ->assertJsonPath('message', 'La categoría fue modificada por otro usuario. Recarga para ver los cambios.');
    $this->patchJson("/api/compensaciones/categorias/{$categoria->id}/estado", ['estado' => 'Activo', 'updated_at' => $versionVieja])
        ->assertStatus(409);

    expect($categoria->fresh()->estado)->toBe('Inactivo');
});

it('permite activar y desactivar una categoría con competencia inactiva y mantiene la advertencia', function () {
    $categoria = CategoriaReconocimientoModel::factory()->inactiva()->conCompetenciaInactiva()->create();
    actuarComoAdmin();

    $this->patchJson("/api/compensaciones/categorias/{$categoria->id}/estado", [
        'estado' => 'Activo',
        'updated_at' => versionDeCategoria($categoria->id),
    ])
        ->assertOk()
        ->assertJsonPath('data.estado', 'Activo')
        ->assertJsonPath('data.competencia_activa', false)
        ->assertJsonPath('data.seleccionable', false);
});

it('valida el cuerpo', function (array $cuerpo, string $campo) {
    $categoria = CategoriaReconocimientoModel::factory()->create();
    actuarComoAdmin();

    $this->patchJson("/api/compensaciones/categorias/{$categoria->id}/estado", $cuerpo)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($campo);
})->with([
    'sin estado' => [['updated_at' => '2026-10-16T10:00:00.000000Z'], 'estado'],
    'estado desconocido' => [['estado' => 'Eliminado', 'updated_at' => '2026-10-16T10:00:00.000000Z'], 'estado'],
    'sin versión' => [['estado' => 'Inactivo'], 'updated_at'],
]);

it('no expone ninguna ruta para eliminar categorías', function () {
    $categoria = CategoriaReconocimientoModel::factory()->create();
    actuarComoAdmin();

    $this->deleteJson("/api/compensaciones/categorias/{$categoria->id}")->assertStatus(405);
    expect(CategoriaReconocimientoModel::query()->count())->toBe(1);
});
