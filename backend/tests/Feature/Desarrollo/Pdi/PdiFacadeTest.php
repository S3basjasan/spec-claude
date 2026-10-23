<?php

use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDePdi;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoDePdi;
use App\Modules\Desarrollo\Presentation\Facades\DesarrolloFacade;

beforeEach(function () {
    pdiFalsos();
});

it('planesActivos devuelve los planes no Cerrados visibles para el colaborador con su avance', function () {
    pdiEnBd(['colaborador' => 10, 'tipo' => TipoDePdi::Mejora, 'estado' => EstadoDePdi::Borrador, 'acuerdo' => false]);
    $carrera = pdiEnBd(['colaborador' => 10, 'tipo' => TipoDePdi::Carrera, 'estado' => EstadoDePdi::Acordado, 'acciones' => ['Cumplida', 'Pendiente'], 'hitos' => [['2020-01-01']]]);
    pdiEnBd(['colaborador' => 10, 'tipo' => TipoDePdi::Talento, 'puesto' => 3, 'estado' => EstadoDePdi::Cerrado]);
    pdiEnBd(['colaborador' => 11, 'estado' => EstadoDePdi::Acordado]);

    $r = DesarrolloFacade::planesActivos(1, 10);

    expect($r)->toHaveCount(1)
        ->and($r[0])->toMatchArray(['pdi_id' => $carrera->id, 'tipo' => 'Carrera', 'estado' => 'Acordado', 'avance' => 50, 'hitos_vencidos' => 1])
        ->and(array_keys($r[0]))->toBe(['pdi_id', 'codigo', 'tipo', 'estado', 'objetivo', 'fecha_inicio', 'fecha_fin', 'avance', 'hitos_vencidos'])
        ->and(DesarrolloFacade::planesActivos(2, 10))->toBe([]);
});

it('pdiPorFiltros devuelve cabeceras sin compromisos ni acciones y aplica los filtros', function () {
    $a = pdiEnBd(['colaborador' => 10, 'estado' => EstadoDePdi::Acordado, 'fin' => '2026-09-30']);
    pdiEnBd(['colaborador' => 11, 'tipo' => TipoDePdi::Carrera, 'estado' => EstadoDePdi::Cerrado, 'fin' => '2026-12-31']);

    $todas = DesarrolloFacade::pdiPorFiltros(1);

    expect($todas)->toHaveCount(2)
        ->and(array_keys($todas[0]))->toBe(['pdi_id', 'codigo', 'colaborador_id', 'tipo', 'estado', 'resultado', 'fecha_inicio', 'fecha_fin', 'cerrado_at', 'compromisos', 'compromisos_cerrados']);
    $ids = fn (array $f) => array_column(DesarrolloFacade::pdiPorFiltros(1, $f), 'pdi_id');
    expect($ids(['tipo' => 'Mejora']))->toBe([$a->id])
        ->and($ids(['estado' => 'Cerrado']))->not->toContain($a->id)
        ->and($ids(['colaborador_id' => 10]))->toBe([$a->id])
        ->and($ids(['fecha_desde' => '2026-10-01']))->not->toContain($a->id)
        ->and($ids(['fecha_hasta' => '2026-09-30']))->toBe([$a->id])
        ->and($ids(['resultado' => 'BrechaCerrada']))->toBe([]);
});

it('existePlanAbierto indica si hay un plan no Cerrado del tipo', function () {
    pdiEnBd(['colaborador' => 10, 'estado' => EstadoDePdi::Borrador]);
    pdiEnBd(['colaborador' => 10, 'tipo' => TipoDePdi::Carrera, 'puesto' => 2, 'estado' => EstadoDePdi::Cerrado]);

    expect(DesarrolloFacade::existePlanAbierto(1, 10, 'Mejora'))->toBeTrue()
        ->and(DesarrolloFacade::existePlanAbierto(1, 10, 'Carrera'))->toBeFalse()
        ->and(DesarrolloFacade::existePlanAbierto(1, 11, 'Mejora'))->toBeFalse()
        ->and(DesarrolloFacade::existePlanAbierto(2, 10, 'Mejora'))->toBeFalse()
        ->and(DesarrolloFacade::existePlanAbierto(1, 10, 'Otro'))->toBeFalse();
});
