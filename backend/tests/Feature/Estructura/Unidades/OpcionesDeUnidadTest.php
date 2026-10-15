<?php

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\UnidadModel;

it('rechaza con 401 sin sesión y con 403 al Usuario', function () {
    $this->getJson('/api/unidades/opciones')->assertUnauthorized();

    actuarComoUsuario();
    $this->getJson('/api/unidades/opciones')
        ->assertForbidden()
        ->assertExactJson(['message' => 'No tienes permisos para realizar esta acción.']);
});

it('devuelve los tipos del catálogo y solo unidades activas en orden jerárquico con su nivel', function () {
    actuarComoAdmin();
    $a = arbolDeEjemplo();
    UnidadModel::factory()->conPadre($a['raiz'])->inactiva()->create(['nombre' => 'Retirada']);

    $respuesta = $this->getJson('/api/unidades/opciones')->assertOk()
        ->assertJsonPath('data.unidades.*.nombre', ['Gerencia general', 'Talento humano', 'Reclutamiento', 'Tecnología', 'Desarrollo'])
        ->assertJsonPath('data.unidades.*.nivel', [0, 1, 2, 1, 2])
        ->assertJsonPath('data.hay_otra_raiz_activa', true)
        ->assertJsonStructure(['data' => ['tipos' => [['id', 'nombre']], 'unidades' => [['id', 'codigo', 'nombre', 'tipo' => ['id', 'nombre'], 'nivel']], 'hay_otra_raiz_activa']]);

    expect(array_column($respuesta->json('data.tipos'), 'nombre'))->toContain('Departamento', 'Gerencia');
});

it('con excluir deja fuera la unidad y todas sus dependientes, directas e indirectas', function () {
    actuarComoAdmin();
    $a = arbolDeEjemplo();

    $this->getJson("/api/unidades/opciones?excluir={$a['tecnologia']->id}")
        ->assertOk()
        ->assertJsonPath('data.unidades.*.nombre', ['Gerencia general', 'Talento humano', 'Reclutamiento']);
    $this->getJson("/api/unidades/opciones?excluir={$a['raiz']->id}")
        ->assertOk()
        ->assertJsonPath('data.unidades', [])
        ->assertJsonPath('data.hay_otra_raiz_activa', false);
});

it('hay_otra_raiz_activa es falso sin raíz activa y distingue la raíz excluida', function () {
    actuarComoAdmin();
    $this->getJson('/api/unidades/opciones')->assertOk()->assertJsonPath('data.hay_otra_raiz_activa', false);

    $a = arbolDeEjemplo();
    $this->getJson("/api/unidades/opciones?excluir={$a['talento']->id}")->assertJsonPath('data.hay_otra_raiz_activa', true);
    $this->getJson("/api/unidades/opciones?excluir={$a['raiz']->id}")->assertJsonPath('data.hay_otra_raiz_activa', false);
});

it('valida el parámetro excluir', function () {
    actuarComoAdmin();

    $this->getJson('/api/unidades/opciones?excluir=abc')->assertUnprocessable();
});

it('opciones no se interpreta como un identificador de unidad', function () {
    actuarComoAdmin();

    $this->getJson('/api/unidades/opciones')->assertOk()->assertJsonStructure(['data' => ['tipos', 'unidades']]);
});
