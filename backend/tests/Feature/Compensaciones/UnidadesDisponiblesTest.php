<?php

it('rechaza con 401 sin sesión', function () {
    $this->getJson('/api/compensaciones/unidades-disponibles')->assertUnauthorized();
});

it('rechaza con 403 a quien no es Gestor', function () {
    actuarComoColaboradorDeUnidad();
    $this->getJson('/api/compensaciones/unidades-disponibles')->assertForbidden();

    actuarComoLiderDeEquipo();
    $this->getJson('/api/compensaciones/unidades-disponibles')->assertForbidden();
});

it('devuelve solo las unidades activas al Gestor', function () {
    actuarComoAdmin();

    $this->getJson('/api/compensaciones/unidades-disponibles')
        ->assertOk()
        ->assertJsonPath('data.*.id', [1, 2, 3])
        ->assertJsonPath('data.*.nombre', ['Operaciones', 'Ventas', 'Finanzas'])
        ->assertJsonStructure(['data' => [['id', 'codigo', 'nombre']]]);
});
