<?php

use App\Modules\Compensaciones\Infrastructure\Persistence\Eloquent\Models\CategoriaReconocimientoModel;
use App\Modules\Compensaciones\Infrastructure\Persistence\Eloquent\Models\IndicadorReconocimientoModel;

it('rechaza con 401 sin sesión', function () {
    $this->getJson('/api/compensaciones/categorias/1')->assertUnauthorized();
});

it('devuelve la categoría con sus indicadores en orden y la versión', function () {
    $categoria = CategoriaReconocimientoModel::factory()->conIndicadores(0)->create();
    foreach ([3 => 'Tercero indicador', 1 => 'Primero indicador', 2 => 'Segundo indicador'] as $orden => $texto) {
        IndicadorReconocimientoModel::query()->create([
            'categoria_reconocimiento_id' => $categoria->id,
            'orden' => $orden,
            'texto' => $texto,
            'texto_normalizado' => mb_strtolower($texto),
        ]);
    }
    actuarComoUsuario();

    $this->getJson("/api/compensaciones/categorias/{$categoria->id}")
        ->assertOk()
        ->assertJsonPath('data.codigo', $categoria->codigo)
        ->assertJsonPath('data.indicadores.*.texto', ['Primero indicador', 'Segundo indicador', 'Tercero indicador'])
        ->assertJsonPath('data.indicadores.*.orden', [1, 2, 3])
        ->assertJsonPath('data.updated_at', versionDeCategoria($categoria->id))
        ->assertJsonPath('data.seleccionable', true);
});

it('devuelve también las categorías inactivas a un Usuario', function () {
    $categoria = CategoriaReconocimientoModel::factory()->inactiva()->create();
    actuarComoUsuario();

    $this->getJson("/api/compensaciones/categorias/{$categoria->id}")
        ->assertOk()
        ->assertJsonPath('data.estado', 'Inactivo')
        ->assertJsonPath('data.seleccionable', false);
});

it('responde 404 si no existe', function () {
    actuarComoUsuario();

    $this->getJson('/api/compensaciones/categorias/9999')->assertNotFound();
    $this->getJson('/api/compensaciones/categorias/abc')->assertNotFound();
});

it('incluye la auditoría solo para el Administrador', function () {
    $categoria = CategoriaReconocimientoModel::factory()->create(['created_by' => 7, 'updated_by' => 8]);

    actuarComoUsuario();
    $this->getJson("/api/compensaciones/categorias/{$categoria->id}")
        ->assertOk()
        ->assertJsonMissingPath('data.created_by')
        ->assertJsonMissingPath('data.updated_by')
        ->assertJsonMissingPath('data.created_at');

    actuarComoAdmin();
    $this->getJson("/api/compensaciones/categorias/{$categoria->id}")
        ->assertOk()
        ->assertJsonPath('data.created_by', 7)
        ->assertJsonPath('data.updated_by', 8)
        ->assertJsonStructure(['data' => ['created_at']]);
});
