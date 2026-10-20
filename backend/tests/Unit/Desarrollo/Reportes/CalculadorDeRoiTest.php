<?php

use App\Modules\Desarrollo\Domain\Services\CalculadorDeRoi;
use App\Modules\Desarrollo\Domain\Services\ProrrateadorPorDepartamento;

it('calcula el ROI de una acción: 5 000 de beneficio sobre 2 000 de costo es 150 %', function () {
    expect((new CalculadorDeRoi)->deAccion(5000.0, 2000.0))->toBe(150.0);
});

it('admite un ROI negativo', function () {
    expect((new CalculadorDeRoi)->deAccion(500.0, 2000.0))->toBe(-75.0);
});

it('no aplica con costo cero o desconocido', function () {
    $c = new CalculadorDeRoi;

    expect($c->deAccion(1000.0, 0.0))->toBeNull()
        ->and($c->deAccion(1000.0, null))->toBeNull();
});

it('calcula el ROI agregado con la suma de beneficios y costos', function () {
    $roi = (new CalculadorDeRoi)->agregado([
        ['beneficio' => 5000.0, 'costo' => 2000.0],
        ['beneficio' => 1000.0, 'costo' => 3000.0],
    ]);

    expect($roi)->toBe(20.0);
});

it('el ROI agregado es nulo sin evaluaciones o con costo total cero', function () {
    $c = new CalculadorDeRoi;

    expect($c->agregado([]))->toBeNull()
        ->and($c->agregado([['beneficio' => 100.0, 'costo' => 0.0]]))->toBeNull();
});

it('mantiene el porcentaje de ROI al prorratear costo y beneficio con la misma proporción', function () {
    $p = new ProrrateadorPorDepartamento;
    $c = new CalculadorDeRoi;
    $proporcion = $p->proporcion(3, 10);

    $roiCompleto = $c->deAccion(5000.0, 2000.0);
    $roiProrrateado = $c->deAccion($p->aplicar(5000.0, $proporcion), $p->aplicar(2000.0, $proporcion));

    expect($proporcion)->toBe(0.3)
        ->and($p->aplicar(2000.0, $proporcion))->toBe(600.0)
        ->and(round($roiProrrateado, 6))->toBe(round($roiCompleto, 6));
});

it('la proporción es 0 sin asistentes y nunca supera 1', function () {
    $p = new ProrrateadorPorDepartamento;

    expect($p->proporcion(0, 10))->toBe(0.0)
        ->and($p->proporcion(5, 0))->toBe(0.0)
        ->and($p->proporcion(10, 10))->toBe(1.0)
        ->and($p->proporcion(12, 10))->toBe(1.0);
});
