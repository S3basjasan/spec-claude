<?php

use App\Modules\Desarrollo\Presentation\Facades\DesarrolloFacade;

beforeEach(function () {
    adecuacionFalsos();
});

it('ultimaAdecuacion devuelve la vigente con sus brechas y null si no hay', function () {
    adecuacionEnBd([['id' => 1, 'objetivo' => 'B', 'valor' => 'D', 'tiempo' => 30]], ['creada' => '2026-01-01 08:00:00']);
    $vigente = adecuacionEnBd([
        ['id' => 1, 'actual' => 'C', 'objetivo' => 'B', 'valor' => 'C', 'tiempo' => 6],
        ['id' => 2, 'objetivo' => 'C', 'valor' => 'C'],
        ['anios' => [5.0, 3.0], 'tiempo' => 18],
    ], ['experiencia' => 5.0]);

    $r = DesarrolloFacade::ultimaAdecuacion(1, 10, 2);

    expect($r)->toMatchArray([
        'adecuacion_id' => $vigente->id, 'codigo' => 'ADE-0002', 'preparacion' => 'NoLista', 'umbral_meses' => 12,
        'puesto_actual_id' => 1, 'puesto_objetivo_id' => 2, 'inscripcion_id' => null,
    ])
        ->and($r['brechas'])->toHaveCount(2)
        ->and($r['brechas'][0])->toMatchArray(['dimension' => 'Competencia', 'elemento_id' => 1, 'diferencia' => -1.0, 'tiempo_cierre_meses' => 6])
        ->and($r['brechas'][1])->toMatchArray(['dimension' => 'Experiencia', 'elemento_id' => null, 'diferencia' => -2.0, 'tiempo_cierre_meses' => 18])
        ->and($r)->not->toHaveKey('observaciones')->not->toHaveKey('created_by');
    expect(DesarrolloFacade::ultimaAdecuacion(1, 10, 3))->toBeNull()
        ->and(DesarrolloFacade::ultimaAdecuacion(2, 10, 2))->toBeNull();
});

it('adecuacionesPorFiltros devuelve solo cabeceras de las vigentes', function () {
    adecuacionEnBd([['objetivo' => 'B', 'valor' => 'D', 'tiempo' => 30]], ['colaborador' => 10, 'puesto' => 2, 'creada' => '2026-01-01 08:00:00']);
    adecuacionEnBd([['objetivo' => 'B', 'valor' => 'C', 'tiempo' => 4]], ['colaborador' => 10, 'puesto' => 2, 'creada' => '2026-02-01 08:00:00']);
    adecuacionEnBd([['objetivo' => 'B', 'valor' => 'B']], ['colaborador' => 12, 'puesto' => 3, 'creada' => '2026-03-01 08:00:00']);

    $todas = DesarrolloFacade::adecuacionesPorFiltros(1);

    expect($todas)->toHaveCount(2)
        ->and(array_keys($todas[0]))->toBe(['adecuacion_id', 'colaborador_id', 'puesto_objetivo_id', 'preparacion', 'brechas', 'tiempo_maximo_cierre_meses', 'fecha']);
    $porColaborador = collect($todas)->keyBy('colaborador_id');
    expect($porColaborador[10])->toMatchArray(['preparacion' => 'EnDesarrollo', 'brechas' => 1, 'tiempo_maximo_cierre_meses' => 4])
        ->and($porColaborador[12])->toMatchArray(['preparacion' => 'Lista', 'brechas' => 0, 'tiempo_maximo_cierre_meses' => null]);
});

it('adecuacionesPorFiltros aplica los filtros', function () {
    adecuacionEnBd([['objetivo' => 'B', 'valor' => 'C', 'tiempo' => 4]], ['colaborador' => 10, 'puesto' => 2, 'creada' => '2026-02-10 08:00:00']);
    adecuacionEnBd([['objetivo' => 'B', 'valor' => 'B']], ['colaborador' => 12, 'puesto' => 3, 'creada' => '2026-03-10 08:00:00']);

    $ids = fn (array $filtros) => array_column(DesarrolloFacade::adecuacionesPorFiltros(1, $filtros), 'colaborador_id');

    expect($ids(['colaborador_id' => 12]))->toBe([12])
        ->and($ids(['puesto_objetivo_id' => 2]))->toBe([10])
        ->and($ids(['preparacion' => 'Lista']))->toBe([12])
        ->and($ids(['fecha_desde' => '2026-03-01']))->toBe([12])
        ->and($ids(['fecha_hasta' => '2026-02-28']))->toBe([10])
        ->and($ids(['departamento_id' => 2]))->toBe([12])
        ->and($ids(['departamento_id' => 1]))->toBe([10])
        ->and($ids(['preparacion' => 'NoLista']))->toBe([]);
});

it('las consultas de la Facade respetan el tenant', function () {
    adecuacionEnBd([['objetivo' => 'B', 'valor' => 'C', 'tiempo' => 4]], ['tenant' => 2]);

    expect(DesarrolloFacade::adecuacionesPorFiltros(1))->toBe([])->and(DesarrolloFacade::adecuacionesPorFiltros(2))->toHaveCount(1);
});
