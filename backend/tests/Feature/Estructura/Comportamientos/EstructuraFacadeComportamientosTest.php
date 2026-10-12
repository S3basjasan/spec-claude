<?php

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;
use App\Modules\Estructura\Presentation\Facades\EstructuraFacade;

it('devuelve los comportamientos de una competencia activa e inactiva (históricos)', function () {
    $activa = registroDe(['nombre' => 'Activa'], ['A' => 2, 'B' => 1, 'C' => 1, 'D' => 1]);
    $inactiva = registroDe(['nombre' => 'Retirada', 'estado' => 'Inactivo']);

    $a = EstructuraFacade::getComportamientos($activa->id);
    $i = EstructuraFacade::getComportamientos($inactiva->id);

    expect($a['nombre'])->toBe('Activa')
        ->and($a['comportamientos']['A'])->toHaveCount(2)
        ->and($i['estado'])->toBe('Inactivo')
        ->and(array_keys($i['comportamientos']))->toBe(['A', 'B', 'C', 'D']);
});

it('devuelve null para una competencia sin registro o inexistente', function () {
    $sin = CompetenciaModel::factory()->create();

    expect(EstructuraFacade::getComportamientos($sin->id))->toBeNull()
        ->and(EstructuraFacade::getComportamientos(999))->toBeNull();
});

it('consulta varias competencias a la vez, incluidas las inactivas, y omite las que no tienen registro', function () {
    $uno = registroDe();
    $dos = registroDe(['estado' => 'Inactivo']);
    $sin = CompetenciaModel::factory()->create();

    $resultado = EstructuraFacade::getComportamientosPorCompetencias([$uno->id, $dos->id, $sin->id, $uno->id]);

    expect($resultado)->toHaveCount(2)
        ->and(EstructuraFacade::getComportamientosPorCompetencias([]))->toBe([]);
});

it('solo las competencias activas con registro sirven para nuevas asignaciones', function () {
    $activa = registroDe(['nombre' => 'B activa']);
    registroDe(['nombre' => 'Retirada', 'estado' => 'Inactivo']);
    $sinRegistro = CompetenciaModel::factory()->create();
    $otraActiva = registroDe(['nombre' => 'A activa']);

    expect(EstructuraFacade::tieneComportamientosActivos($activa->id))->toBeTrue()
        ->and(EstructuraFacade::tieneComportamientosActivos($sinRegistro->id))->toBeFalse()
        ->and(array_column(EstructuraFacade::getComportamientosActivos(), 'nombre'))->toBe(['A activa', 'B activa'])
        ->and(EstructuraFacade::getComportamientosActivos()[0]['competencia_id'])->toBe($otraActiva->id);
});

it('al desactivar la competencia deja de ser válida para nuevas asignaciones pero sigue consultable', function () {
    $competencia = registroDe();
    actuarComoAdmin();

    $this->patchJson("/api/competencias/{$competencia->id}/estado", ['estado' => 'Inactivo'])->assertOk();

    expect(EstructuraFacade::tieneComportamientosActivos($competencia->id))->toBeFalse()
        ->and(EstructuraFacade::getComportamientos($competencia->id)['estado'])->toBe('Inactivo');

    $this->patchJson("/api/competencias/{$competencia->id}/estado", ['estado' => 'Activo'])->assertOk();
    expect(EstructuraFacade::tieneComportamientosActivos($competencia->id))->toBeTrue();
});
