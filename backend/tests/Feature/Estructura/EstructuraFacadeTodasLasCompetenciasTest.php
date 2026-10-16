<?php

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;
use App\Modules\Estructura\Presentation\Facades\EstructuraFacade;

it('getTodasLasCompetencias devuelve activas e inactivas, sin grados, ordenadas por nombre', function () {
    CompetenciaModel::factory()->create(['nombre' => 'Zeta', 'estado' => 'Activo']);
    CompetenciaModel::factory()->inactiva()->create(['nombre' => 'Alfa']);

    $todas = EstructuraFacade::getTodasLasCompetencias();

    expect(array_column($todas, 'nombre'))->toBe(['Alfa', 'Zeta'])
        ->and(array_column($todas, 'estado'))->toBe(['Inactivo', 'Activo'])
        ->and(array_keys($todas[0]))->toBe(['id', 'codigo', 'nombre', 'descripcion', 'tipo', 'estado'])
        ->and($todas[0]['codigo'])->toStartWith('COMP-');
});

it('getTodasLasCompetencias devuelve una lista vacía sin competencias', function () {
    expect(EstructuraFacade::getTodasLasCompetencias())->toBe([]);
});
