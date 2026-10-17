<?php

use App\Modules\Desarrollo\Domain\Services\CalculadorDeAsignacion;
use App\Modules\Desarrollo\Domain\Services\CalculadorDeIntento;
use App\Modules\Desarrollo\Domain\Services\CalculadorDeResumen;

it('el puntaje son las respuestas correctas y el intento termina al responder todas', function () {
    $calculador = new CalculadorDeIntento;
    $respuestas = [['es_correcta' => true], ['es_correcta' => false], ['es_correcta' => true]];

    expect($calculador->puntaje($respuestas))->toBe(2)
        ->and($calculador->terminado(2, 3))->toBeFalse()
        ->and($calculador->terminado(3, 3))->toBeTrue()
        ->and($calculador->terminado(0, 0))->toBeFalse()
        ->and($calculador->opcionCorrectaId([['id' => 4, 'es_correcta' => false], ['id' => 5, 'es_correcta' => true]]))->toBe(5);
});

it('asigna los publicados con brecha y los iniciados, nunca los completados', function () {
    $r = (new CalculadorDeAsignacion)->asignados([1, 2, 3], [4, 2], [3, 4]);

    expect($r)->toBe([1 => 'brecha', 2 => 'iniciado']);
});

it('un contenido iniciado y con brecha se muestra como iniciado', function () {
    expect((new CalculadorDeAsignacion)->asignados([7], [7], []))->toBe([7 => 'iniciado']);
});

it('sin brechas ni iniciados no hay asignados', function () {
    expect((new CalculadorDeAsignacion)->asignados([], [], [1]))->toBe([]);
});

it('las horas acumuladas suman la duración de los completados', function () {
    $resumen = (new CalculadorDeResumen)->resumir(2, 1, 60);

    expect($resumen)->toBe(['completados' => 2, 'en_progreso' => 1, 'horas_acumuladas' => 1.0, 'certificados' => 2])
        ->and((new CalculadorDeResumen)->resumir(0, 0, 0)['horas_acumuladas'])->toBe(0.0)
        ->and((new CalculadorDeResumen)->resumir(1, 0, 25)['horas_acumuladas'])->toBe(0.4);
});
