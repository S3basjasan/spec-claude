<?php

use App\Modules\Compensaciones\Infrastructure\Persistence\Eloquent\Models\AuditoriaCategoriaModel;
use App\Modules\Compensaciones\Infrastructure\Persistence\Eloquent\Models\CategoriaReconocimientoModel;

it('rechaza por API directa una puntuación mayor al máximo con el error en puntuacion_base', function () {
    actuarComoAdmin();
    $categoria = CategoriaReconocimientoModel::factory()->create(['puntuacion_base' => 50]);

    $this->postJson('/api/compensaciones/categorias', datosCategoria(null, ['puntuacion_base' => 101]))
        ->assertUnprocessable()
        ->assertExactJson([
            'message' => 'La puntuación base debe estar entre 1 y 100.',
            'errors' => ['puntuacion_base' => ['La puntuación base debe estar entre 1 y 100.']],
        ]);
    $this->putJson("/api/compensaciones/categorias/{$categoria->id}", cuerpoDeCategoria($categoria, ['puntuacion_base' => 101]))
        ->assertUnprocessable()
        ->assertExactJson([
            'message' => 'La puntuación base debe estar entre 1 y 100.',
            'errors' => ['puntuacion_base' => ['La puntuación base debe estar entre 1 y 100.']],
        ]);

    expect(CategoriaReconocimientoModel::query()->count())->toBe(1)
        ->and($categoria->fresh()->puntuacion_base)->toBe(50)
        ->and(AuditoriaCategoriaModel::query()->count())->toBe(0);
});

it('rechaza valores no válidos en crear y editar sin guardar nada', function (mixed $valor) {
    actuarComoAdmin();
    $categoria = CategoriaReconocimientoModel::factory()->create(['puntuacion_base' => 50]);

    $this->postJson('/api/compensaciones/categorias', datosCategoria(null, ['puntuacion_base' => $valor]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('puntuacion_base');
    $this->putJson("/api/compensaciones/categorias/{$categoria->id}", cuerpoDeCategoria($categoria, ['puntuacion_base' => $valor]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('puntuacion_base');

    expect(CategoriaReconocimientoModel::query()->count())->toBe(1)
        ->and($categoria->fresh()->puntuacion_base)->toBe(50)
        ->and(AuditoriaCategoriaModel::query()->count())->toBe(0);
})->with([1.5, 'abc', null, 0, -5, 1001, '']);

it('mantiene 401 sin sesión y 403 a un Usuario', function () {
    $this->postJson('/api/compensaciones/categorias', datosCategoria(null, ['puntuacion_base' => 101]))->assertUnauthorized();

    actuarComoUsuario();
    $this->postJson('/api/compensaciones/categorias', datosCategoria(null, ['puntuacion_base' => 101]))->assertForbidden();
});
