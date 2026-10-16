<?php

use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\AreaFormativaModel;

it('lista las áreas ordenadas por nombre para administrador y colaborador', function () {
    AreaFormativaModel::factory()->create(['nombre' => 'Zeta']);
    AreaFormativaModel::factory()->create(['nombre' => 'Alfa']);

    actuarComoAdmin();
    expect(array_column($this->getJson('/api/catalogo-formativo/areas')->assertOk()->json('data'), 'nombre'))->toBe(['Alfa', 'Zeta']);

    actuarComoUsuario();
    expect(array_column($this->getJson('/api/catalogo-formativo/areas')->assertOk()->json('data'), 'nombre'))->toBe(['Alfa', 'Zeta']);
});

it('exige sesión para listar las áreas', function () {
    $this->getJson('/api/catalogo-formativo/areas')->assertUnauthorized();
});
