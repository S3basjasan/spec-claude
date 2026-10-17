<?php

use App\Modules\Compensaciones\Infrastructure\Persistence\Eloquent\Models\CategoriaReconocimientoModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;

it('entrega la primera página del listado en menos de 2 segundos con 500 categorías', function () {
    $competencia = CompetenciaModel::factory()->create();
    CategoriaReconocimientoModel::factory()->count(500)->conIndicadores(1)->create(['competencia_id' => $competencia->id]);
    actuarComoUsuario();

    $inicio = microtime(true);
    $respuesta = $this->getJson('/api/compensaciones/categorias');
    $segundos = microtime(true) - $inicio;

    $respuesta->assertOk()
        ->assertJsonCount(15, 'data')
        ->assertJsonPath('meta.total', 500)
        ->assertJsonPath('meta.last_page', 34);

    expect($segundos)->toBeLessThan(2.0);
});
