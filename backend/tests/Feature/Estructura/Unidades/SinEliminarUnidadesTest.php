<?php

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\UnidadModel;
use Illuminate\Support\Facades\Route;

it('bajo api/unidades solo existen siete rutas y ninguna es DELETE', function () {
    $rutas = collect(Route::getRoutes()->getRoutes())->filter(fn ($r) => str_starts_with($r->uri(), 'api/unidades'));

    expect($rutas)->toHaveCount(7)
        ->and($rutas->flatMap(fn ($r) => $r->methods())->unique()->values()->sort()->values()->all())->toBe(['GET', 'HEAD', 'PATCH', 'POST', 'PUT']);
});

it('DELETE responde 404 o 405 incluso al Administrador y no elimina ninguna unidad', function () {
    actuarComoAdmin();
    $a = arbolDeEjemplo();

    expect([404, 405])->toContain($this->deleteJson("/api/unidades/{$a['reclutamiento']->id}")->status());
    expect(UnidadModel::query()->count())->toBe(5);
});

it('ninguna operación de la API elimina filas: desactivar, reubicar y editar conservan todas las unidades', function () {
    actuarComoAdmin();
    $a = arbolDeEjemplo();

    $this->patchJson("/api/unidades/{$a['reclutamiento']->id}/estado", ['estado' => 'Inactiva'])->assertOk();
    $this->putJson("/api/unidades/{$a['tecnologia']->id}", cuerpoDeUnidad($a['tecnologia'], ['unidad_padre_id' => $a['talento']->id]))->assertOk();
    $this->postJson('/api/unidades', datosUnidad($a['raiz']->id))->assertCreated();

    expect(UnidadModel::query()->count())->toBe(6);
});
