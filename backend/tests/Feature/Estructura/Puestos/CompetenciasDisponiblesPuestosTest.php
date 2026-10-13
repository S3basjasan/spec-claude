<?php

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;

it('exige sesión y deniega al Usuario', function () {
    $this->getJson('/api/puestos/competencias-disponibles')->assertUnauthorized();

    actuarComoUsuario();
    $this->getJson('/api/puestos/competencias-disponibles')->assertForbidden();
});

it('entrega al Administrador solo las competencias activas con sus cuatro grados, ordenadas por nombre', function () {
    CompetenciaModel::factory()->create(['nombre' => 'Zeta']);
    CompetenciaModel::factory()->create(['nombre' => 'Alfa']);
    CompetenciaModel::factory()->inactiva()->create(['nombre' => 'Adaptabilidad']);
    actuarComoAdmin();

    $respuesta = $this->getJson('/api/puestos/competencias-disponibles')->assertOk();

    expect(array_column($respuesta->json('data'), 'nombre'))->toBe(['Alfa', 'Zeta'])
        ->and(array_keys($respuesta->json('data.0')))->toBe(['competencia_id', 'codigo', 'nombre', 'tipo', 'grados'])
        ->and(array_column($respuesta->json('data.0.grados'), 'grado'))->toBe(['A', 'B', 'C', 'D'])
        ->and($respuesta->json('data.0.grados.0'))->toHaveKeys(['grado', 'descripcion']);
});

it('no excluye las competencias que ya están en algún puesto', function () {
    $puesto = puestoDe(['nombre' => 'Analista']);
    $asignada = $puesto->competenciasAsignadas()->first()->competencia_id;
    actuarComoAdmin();

    $ids = array_column($this->getJson('/api/puestos/competencias-disponibles')->json('data'), 'competencia_id');

    expect($ids)->toContain($asignada);
});

it('la ruta competencias-disponibles nunca se interpreta como un identificador', function () {
    actuarComoAdmin();

    $this->getJson('/api/puestos/competencias-disponibles')->assertOk()->assertJsonStructure(['data']);
});
