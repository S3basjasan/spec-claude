<?php

use App\Modules\Desarrollo\Domain\Services\CalculadorDeCobertura;

it('calcula el 40 % con 4 de 10 competencias activas cubiertas', function () {
    $mapa = [1 => 2, 2 => 1, 3 => 5, 4 => 1, 5 => 0, 6 => 0, 7 => 0, 8 => 0, 9 => 0, 10 => 0];

    $cobertura = (new CalculadorDeCobertura)->calcular($mapa);

    expect($cobertura->activasTotales)->toBe(10)
        ->and($cobertura->activasCubiertas)->toBe(4)
        ->and($cobertura->porcentaje)->toBe(40);
});

it('devuelve 0 % sin competencias activas, sin error', function () {
    $cobertura = (new CalculadorDeCobertura)->calcular([]);

    expect($cobertura->activasTotales)->toBe(0)
        ->and($cobertura->activasCubiertas)->toBe(0)
        ->and($cobertura->porcentaje)->toBe(0);
});

it('devuelve 100 % cuando todas están cubiertas', function () {
    expect((new CalculadorDeCobertura)->calcular([1 => 1, 2 => 3])->porcentaje)->toBe(100);
});

it('una competencia sin recursos disponibles no está cubierta', function () {
    $cobertura = (new CalculadorDeCobertura)->calcular([1 => 0]);

    expect($cobertura->activasCubiertas)->toBe(0)->and($cobertura->porcentaje)->toBe(0);
});

it('redondea al entero más cercano', function () {
    expect((new CalculadorDeCobertura)->calcular([1 => 1, 2 => 0, 3 => 0])->porcentaje)->toBe(33)
        ->and((new CalculadorDeCobertura)->calcular([1 => 1, 2 => 1, 3 => 0])->porcentaje)->toBe(67);
});
