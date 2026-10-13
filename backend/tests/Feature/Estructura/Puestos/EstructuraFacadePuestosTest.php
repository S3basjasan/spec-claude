<?php

use App\Modules\Estructura\Presentation\Facades\EstructuraFacade;

it('getPuesto devuelve el puesto con sus listas, también inactivo, y null si no existe', function () {
    $activo = puestoDe(['nombre' => 'Analista']);
    $inactivo = puestoDe(['nombre' => 'Viejo', 'estado' => 'Inactivo']);

    $puesto = EstructuraFacade::getPuesto($activo->id);

    expect(array_keys($puesto))->toBe(['id', 'codigo', 'nombre', 'descripcion', 'mision', 'estado', 'funciones', 'requisitos', 'competencias'])
        ->and($puesto['id'])->toBe($activo->id)
        ->and($puesto['codigo'])->toBe($activo->fresh()->codigo)
        ->and($puesto['estado'])->toBe('Activo')
        ->and($puesto['funciones'][0])->toBe(['orden' => 1, 'descripcion' => $puesto['funciones'][0]['descripcion'], 'tipo' => 'Esencial'])
        ->and(array_keys($puesto['requisitos'][0]))->toBe(['tipo', 'descripcion'])
        ->and(array_keys($puesto['competencias'][0]))->toBe(['competencia_id', 'codigo', 'nombre', 'tipo', 'estado', 'grado_requerido'])
        ->and(EstructuraFacade::getPuesto($inactivo->id)['estado'])->toBe('Inactivo')
        ->and(EstructuraFacade::getPuesto(999999))->toBeNull();
});

it('devuelve solo arreglos, nunca modelos ni entidades', function () {
    $puesto = puestoDe();

    $arreglo = EstructuraFacade::getPuesto($puesto->id);

    expect($arreglo)->toBeArray()
        ->and($arreglo['funciones'])->each->toBeArray()
        ->and($arreglo['requisitos'])->each->toBeArray()
        ->and($arreglo['competencias'])->each->toBeArray();
});

it('getPuestosPorIds devuelve los que existen, incluidos los inactivos', function () {
    $activo = puestoDe(['nombre' => 'Analista']);
    $inactivo = puestoDe(['nombre' => 'Viejo', 'estado' => 'Inactivo']);

    $puestos = EstructuraFacade::getPuestosPorIds([$activo->id, $inactivo->id, 999999, $activo->id]);

    expect(array_column($puestos, 'id'))->toBe([$activo->id, $inactivo->id])
        ->and(EstructuraFacade::getPuestosPorIds([]))->toBe([]);
});

it('existePuesto y puestoEstaActivo distinguen activo, inactivo e inexistente', function () {
    $activo = puestoDe(['nombre' => 'Analista']);
    $inactivo = puestoDe(['nombre' => 'Viejo', 'estado' => 'Inactivo']);

    expect(EstructuraFacade::existePuesto($activo->id))->toBeTrue()
        ->and(EstructuraFacade::existePuesto($inactivo->id))->toBeTrue()
        ->and(EstructuraFacade::existePuesto(999999))->toBeFalse()
        ->and(EstructuraFacade::puestoEstaActivo($activo->id))->toBeTrue()
        ->and(EstructuraFacade::puestoEstaActivo($inactivo->id))->toBeFalse()
        ->and(EstructuraFacade::puestoEstaActivo(999999))->toBeFalse();
});

it('getPuestosActivos devuelve solo los activos ordenados por nombre con los campos del contrato', function () {
    $zeta = puestoDe(['nombre' => 'Zeta']);
    $alfa = puestoDe(['nombre' => 'Alfa']);
    puestoDe(['nombre' => 'Viejo', 'estado' => 'Inactivo']);

    $activos = EstructuraFacade::getPuestosActivos();

    expect(array_column($activos, 'nombre'))->toBe(['Alfa', 'Zeta'])
        ->and(array_keys($activos[0]))->toBe(['id', 'codigo', 'nombre', 'descripcion'])
        ->and($activos[0]['id'])->toBe($alfa->id)
        ->and($activos[1]['id'])->toBe($zeta->id);
});
