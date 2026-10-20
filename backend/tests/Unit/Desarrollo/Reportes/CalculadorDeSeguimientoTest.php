<?php

use App\Modules\Desarrollo\Domain\Services\CalculadorDeSeguimiento;

/** @param  list<int>  $aplicaciones calificaciones de aplicación del hito (apoyo = 3 en todas) */
function respuestasDelHito(int $hito, array $aplicaciones, int $apoyo = 3): array
{
    return array_map(fn (int $a) => ['hito' => $hito, 'aplicacion' => $a, 'apoyo' => $apoyo], $aplicaciones);
}

function seguimiento(int $elegibles, array $respuestas): array
{
    return (new CalculadorDeSeguimiento)->calcular($elegibles, $respuestas, new DateTimeImmutable('2026-03-10'));
}

it('calcula tasa de respuesta y promedios por hito', function () {
    $r = seguimiento(10, [...respuestasDelHito(30, [5, 4, 3, 4], 2), ...respuestasDelHito(60, [3, 3])]);

    expect($r['hitos'][0])->toBe(['hito' => 30, 'vencimiento' => '2026-04-09', 'respuestas' => 4, 'tasa_respuesta' => 40.0, 'promedio_aplicacion' => 4.0, 'promedio_apoyo' => 2.0])
        ->and($r['hitos'][1]['respuestas'])->toBe(2)->and($r['hitos'][1]['tasa_respuesta'])->toBe(20.0)
        ->and($r['hitos'][2]['respuestas'])->toBe(0);
});

it('un hito sin respuestas tiene promedios nulos', function () {
    $r = seguimiento(5, []);

    expect($r['hitos'][2]['promedio_aplicacion'])->toBeNull()->and($r['hitos'][2]['promedio_apoyo'])->toBeNull()
        ->and($r['hitos'][2]['tasa_respuesta'])->toBe(0.0);
});

it('la tasa de respuesta es nula sin colaboradores elegibles', function () {
    expect(seguimiento(0, [])['hitos'][0]['tasa_respuesta'])->toBeNull();
});

it('marca la alerta con una caída de exactamente 1 punto y 3 respuestas en ambos hitos', function () {
    $r = seguimiento(5, [...respuestasDelHito(30, [5, 4, 3]), ...respuestasDelHito(60, [4, 3, 2])]);

    expect($r['alerta_reingreso'])->toBeTrue();
});

it('marca la alerta con una caída mayor', function () {
    $r = seguimiento(5, [...respuestasDelHito(30, [5, 5, 5]), ...respuestasDelHito(60, [3, 3, 3])]);

    expect($r['alerta_reingreso'])->toBeTrue();
});

it('no marca la alerta con una caída menor de 1 punto', function () {
    $r = seguimiento(5, [...respuestasDelHito(30, [5, 4, 4]), ...respuestasDelHito(60, [4, 4, 4])]);

    expect($r['alerta_reingreso'])->toBeFalse();
});

it('no marca la alerta con menos de 3 respuestas en alguno de los dos hitos', function () {
    $pocas = seguimiento(5, [...respuestasDelHito(30, [5, 5, 5]), ...respuestasDelHito(60, [1, 1])]);
    $pocas30 = seguimiento(5, [...respuestasDelHito(30, [5, 5]), ...respuestasDelHito(60, [1, 1, 1])]);

    expect($pocas['alerta_reingreso'])->toBeFalse()->and($pocas30['alerta_reingreso'])->toBeFalse();
});

it('detecta la alerta entre los hitos 60 y 90', function () {
    $r = seguimiento(5, [...respuestasDelHito(60, [5, 5, 5]), ...respuestasDelHito(90, [3, 4, 3])]);

    expect($r['alerta_reingreso'])->toBeTrue();
});

it('no marca la alerta si el promedio sube', function () {
    $r = seguimiento(5, [...respuestasDelHito(30, [2, 2, 2]), ...respuestasDelHito(60, [5, 5, 5])]);

    expect($r['alerta_reingreso'])->toBeFalse();
});

it('no compara hitos no consecutivos (30 con 90) sin el intermedio', function () {
    $r = seguimiento(5, [...respuestasDelHito(30, [5, 5, 5]), ...respuestasDelHito(90, [1, 1, 1])]);

    expect($r['alerta_reingreso'])->toBeFalse();
});
