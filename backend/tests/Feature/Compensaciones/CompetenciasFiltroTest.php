<?php

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;

it('rechaza con 401 sin sesión', function () {
    $this->getJson('/api/compensaciones/competencias-filtro')->assertUnauthorized();
    $this->getJson('/api/compensaciones/competencias-disponibles')->assertUnauthorized();
});

it('ofrece a un Usuario solo las competencias activas, por nombre', function () {
    CompetenciaModel::factory()->create(['nombre' => 'Zeta']);
    CompetenciaModel::factory()->create(['nombre' => 'Alfa']);
    CompetenciaModel::factory()->inactiva()->create(['nombre' => 'Retirada']);
    actuarComoUsuario();

    $this->getJson('/api/compensaciones/competencias-filtro')
        ->assertOk()
        ->assertJsonPath('data.*.nombre', ['Alfa', 'Zeta'])
        ->assertJsonStructure(['data' => [['id', 'codigo', 'nombre', 'tipo', 'estado']]]);
});

it('ofrece al Administrador todas las competencias con su estado', function () {
    CompetenciaModel::factory()->create(['nombre' => 'Activa uno']);
    CompetenciaModel::factory()->inactiva()->create(['nombre' => 'Inactiva uno']);
    actuarComoAdmin();

    $this->getJson('/api/compensaciones/competencias-filtro')
        ->assertOk()
        ->assertJsonPath('data.*.nombre', ['Activa uno', 'Inactiva uno'])
        ->assertJsonPath('data.*.estado', ['Activo', 'Inactivo']);
});

it('ofrece para el formulario solo las competencias activas y solo al Administrador', function () {
    CompetenciaModel::factory()->create(['nombre' => 'Activa uno']);
    CompetenciaModel::factory()->inactiva()->create(['nombre' => 'Inactiva uno']);

    actuarComoUsuario();
    $this->getJson('/api/compensaciones/competencias-disponibles')->assertForbidden();

    actuarComoAdmin();
    $this->getJson('/api/compensaciones/competencias-disponibles')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.nombre', 'Activa uno');
});
