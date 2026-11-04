<?php

use App\Modules\Compensaciones\Infrastructure\Persistence\Eloquent\Models\AuditoriaCategoriaModel;
use App\Modules\Compensaciones\Infrastructure\Persistence\Eloquent\Models\CategoriaReconocimientoModel;
use Carbon\Carbon;

require_once __DIR__.'/../../Support/Compensaciones/ParametrosHelpers.php';

afterEach(fn () => Carbon::setTestNow());

function cambiarEstadoDeCategoria(CategoriaReconocimientoModel $categoria, string $estado)
{
    return test()->patchJson("/api/compensaciones/categorias/{$categoria->id}/estado", [
        'estado' => $estado,
        'updated_at' => versionDeCategoria($categoria->id),
    ]);
}

it('rechaza activar una inactiva con puntuación sobre el máximo y sigue inactiva', function () {
    actuarComoAdmin();
    $categoria = CategoriaReconocimientoModel::factory()->inactiva()->create(['puntuacion_base' => 150]);

    cambiarEstadoDeCategoria($categoria, 'Activo')
        ->assertUnprocessable()
        ->assertExactJson([
            'message' => 'Baja primero la puntuación a 100 o menos.',
            'errors' => ['puntuacion_base' => ['Baja primero la puntuación a 100 o menos.']],
        ]);

    expect($categoria->fresh()->estado)->toBe('Inactivo')
        ->and(AuditoriaCategoriaModel::query()->count())->toBe(0);
});

it('activa una inactiva con puntuación igual al máximo', function () {
    actuarComoAdmin();
    $categoria = CategoriaReconocimientoModel::factory()->inactiva()->create(['puntuacion_base' => 100]);

    cambiarEstadoDeCategoria($categoria, 'Activo')->assertOk()->assertJsonPath('data.estado', 'Activo');
});

it('permite activar tras bajar la puntuación', function () {
    actuarComoAdmin();
    $categoria = CategoriaReconocimientoModel::factory()->inactiva()->create(['puntuacion_base' => 150]);

    $this->putJson("/api/compensaciones/categorias/{$categoria->id}", cuerpoDeCategoria($categoria, ['puntuacion_base' => 100]))->assertOk();

    cambiarEstadoDeCategoria($categoria, 'Activo')->assertOk()->assertJsonPath('data.estado', 'Activo');
});

it('pedir Activo a una ya activa por encima del tope devuelve la categoría sin cambios', function () {
    actuarComoAdmin();
    $categoria = CategoriaReconocimientoModel::factory()->create(['puntuacion_base' => 150]);

    cambiarEstadoDeCategoria($categoria, 'Activo')->assertOk()->assertJsonPath('data.estado', 'Activo');

    expect(AuditoriaCategoriaModel::query()->count())->toBe(0);
});

it('desactivar siempre se permite, también por encima del tope', function () {
    actuarComoAdmin();
    $categoria = CategoriaReconocimientoModel::factory()->create(['puntuacion_base' => 150]);

    cambiarEstadoDeCategoria($categoria, 'Inactivo')->assertOk()->assertJsonPath('data.estado', 'Inactivo');
});

it('responde 409 por versión desactualizada antes que 422 por puntuación', function () {
    actuarComoAdmin();
    $categoria = CategoriaReconocimientoModel::factory()->inactiva()->create(['puntuacion_base' => 150]);
    $versionVieja = versionDeCategoria($categoria->id);
    $this->putJson("/api/compensaciones/categorias/{$categoria->id}", cuerpoDeCategoria($categoria, ['puntuacion_base' => 90]))->assertOk();

    $this->patchJson("/api/compensaciones/categorias/{$categoria->id}/estado", ['estado' => 'Activo', 'updated_at' => $versionVieja])
        ->assertStatus(409);
});

it('toma el máximo del cambio programado cuando es menor', function () {
    actuarComoAdmin();
    Carbon::setTestNow('2026-11-10 10:00:00');
    putParametro('puntos_por_colaborador', 60)->assertOk();
    $categoria = CategoriaReconocimientoModel::factory()->inactiva()->create(['puntuacion_base' => 80]);

    cambiarEstadoDeCategoria($categoria, 'Activo')
        ->assertUnprocessable()
        ->assertJsonPath('errors.puntuacion_base.0', 'Baja primero la puntuación a 60 o menos.');
});

it('mantiene 401 sin sesión y 403 a un Usuario', function () {
    $categoria = CategoriaReconocimientoModel::factory()->inactiva()->create(['puntuacion_base' => 150]);
    $cuerpo = ['estado' => 'Activo', 'updated_at' => versionDeCategoria($categoria->id)];

    $this->patchJson("/api/compensaciones/categorias/{$categoria->id}/estado", $cuerpo)->assertUnauthorized();

    actuarComoUsuario();
    $this->patchJson("/api/compensaciones/categorias/{$categoria->id}/estado", $cuerpo)->assertForbidden();
});
