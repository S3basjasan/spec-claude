<?php

use App\Modules\GestionPersonal\Domain\Services\CatalogoDePoliticas;
use App\Modules\GestionPersonal\Domain\Services\DiferenciasDePolitica;
use App\Modules\GestionPersonal\Domain\ValueObjects\TipoDePolitica;

beforeEach(fn () => $this->diferencias = new DiferenciasDePolitica(new CatalogoDePoliticas));

it('sin vigente todos los parámetros se marcan como nuevos', function () {
    $d = $this->diferencias->entre(TipoDePolitica::Permisos, ['descripcion' => 'A', 'parametros' => parametrosValidos('Permisos')], null);

    expect(array_column($d, 'clave'))->toBe(['descripcion', 'disponible', 'justificacion_obligatoria', 'documento_obligatorio', 'anticipacion_dias']);
});

it('señala solo el parámetro que cambia y nada si no hay cambios', function () {
    $vigente = ['descripcion' => 'A', 'parametros' => parametrosValidos('Permisos')];
    $propuesta = ['descripcion' => 'A', 'parametros' => ['anticipacion_dias' => 3] + parametrosValidos('Permisos')];

    $d = $this->diferencias->entre(TipoDePolitica::Permisos, $propuesta, $vigente);

    expect($d)->toHaveCount(1)->and($d[0]['clave'])->toBe('anticipacion_dias')->and($d[0]['anterior'])->toBe(2)->and($d[0]['nuevo'])->toBe(3)
        ->and($this->diferencias->entre(TipoDePolitica::Permisos, $vigente, $vigente))->toBe([]);
});

it('compara las tareas de Offboarding como lista ordenada', function () {
    $a = parametrosValidos('Offboarding');
    $b = ['tareas' => array_reverse($a['tareas'])];

    $d = $this->diferencias->entre(TipoDePolitica::Offboarding, ['descripcion' => 'x', 'parametros' => $b], ['descripcion' => 'x', 'parametros' => $a]);

    expect($d)->toHaveCount(1)->and($d[0]['clave'])->toBe('tareas');
});

it('Ausencias no compara anticipación', function () {
    $d = $this->diferencias->entre(TipoDePolitica::Ausencias, ['descripcion' => 'x', 'parametros' => parametrosValidos('Ausencias')], null);

    expect(array_column($d, 'clave'))->not->toContain('anticipacion_dias');
});
