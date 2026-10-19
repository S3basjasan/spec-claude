<?php

use App\Modules\Desarrollo\Domain\Services\CalculadorDeBrechasCerradas;
use App\Modules\Desarrollo\Domain\ValueObjects\PeriodoReporte;

/** Evaluación de una competencia de un colaborador en una DNF. */
function ev(int $dnf, string $fecha, int $colaborador, int $competencia, string $estado, ?string $actual = null, string $requerido = 'B'): array
{
    return [
        'dnf_id' => $dnf, 'fecha' => $fecha, 'colaborador_id' => $colaborador, 'competencia_id' => $competencia,
        'estado' => $estado, 'nivel_requerido' => $requerido, 'nivel_actual' => $actual,
    ];
}

function periodo(string $desde, string $hasta): PeriodoReporte
{
    return new PeriodoReporte(new DateTimeImmutable($desde), new DateTimeImmutable($hasta));
}

it('detecta una brecha y la cierra en la primera DNF posterior que cumple o supera', function (string $estadoFinal) {
    $c = new CalculadorDeBrechasCerradas;
    $brechas = $c->brechas([ev(1, '2026-01-10', 7, 3, 'ConBrecha'), ev(2, '2026-06-10', 7, 3, $estadoFinal)]);

    expect($brechas)->toBe([['colaborador_id' => 7, 'competencia_id' => 3, 'detectada_en' => '2026-01-10', 'cerrada_en' => '2026-06-10']]);
})->with(['Cumple', 'Supera']);

it('ignora SinEvaluacion y mantiene la brecha abierta', function () {
    $c = new CalculadorDeBrechasCerradas;
    $brechas = $c->brechas([
        ev(1, '2026-01-10', 7, 3, 'ConBrecha'),
        ev(2, '2026-03-10', 7, 3, 'SinEvaluacion'),
        ev(3, '2026-06-10', 7, 3, 'ConBrecha'),
    ]);

    expect($brechas)->toBe([['colaborador_id' => 7, 'competencia_id' => 3, 'detectada_en' => '2026-01-10', 'cerrada_en' => null]]);
});

it('una DNF con una sola evaluación no cierra nada, ni siquiera si cumple', function () {
    $c = new CalculadorDeBrechasCerradas;

    expect($c->brechas([ev(1, '2026-01-10', 7, 3, 'Cumple')]))->toBe([])
        ->and($c->brechas([ev(1, '2026-01-10', 7, 3, 'ConBrecha')]))->toHaveCount(1);
});

it('una nueva brecha tras un cierre cuenta como otra brecha', function () {
    $c = new CalculadorDeBrechasCerradas;
    $brechas = $c->brechas([
        ev(1, '2026-01-10', 7, 3, 'ConBrecha'),
        ev(2, '2026-03-10', 7, 3, 'Cumple'),
        ev(3, '2026-06-10', 7, 3, 'ConBrecha'),
        ev(4, '2026-09-10', 7, 3, 'Supera'),
    ]);

    expect($brechas)->toHaveCount(2)
        ->and($brechas[0]['cerrada_en'])->toBe('2026-03-10')
        ->and($brechas[1]['detectada_en'])->toBe('2026-06-10')
        ->and($brechas[1]['cerrada_en'])->toBe('2026-09-10');
});

it('trata cada colaborador y competencia por separado', function () {
    $c = new CalculadorDeBrechasCerradas;
    $brechas = $c->brechas([
        ev(1, '2026-01-10', 7, 3, 'ConBrecha'), ev(1, '2026-01-10', 8, 3, 'ConBrecha'), ev(1, '2026-01-10', 7, 4, 'ConBrecha'),
        ev(2, '2026-05-10', 7, 3, 'Cumple'),
    ]);

    expect(collect($brechas)->whereNotNull('cerrada_en'))->toHaveCount(1)
        ->and($brechas)->toHaveCount(3);
});

it('detectadas del período cuenta solo la primera aparición dentro del período', function () {
    $c = new CalculadorDeBrechasCerradas;
    $brechas = $c->brechas([
        ev(1, '2025-11-10', 7, 3, 'ConBrecha'), ev(2, '2026-02-10', 7, 3, 'ConBrecha'), ev(3, '2026-04-10', 7, 3, 'Cumple'),
        ev(2, '2026-02-10', 8, 3, 'ConBrecha'),
    ]);

    expect($c->detectadasEn($brechas, periodo('2026-01-01', '2026-12-31')))->toBe(1)
        ->and($c->detectadasEn($brechas, periodo('2025-01-01', '2025-12-31')))->toBe(1);
});

it('las cerradas pueden superar a las detectadas cuando la brecha nació en un período anterior', function () {
    $c = new CalculadorDeBrechasCerradas;
    $brechas = $c->brechas([ev(1, '2025-11-10', 7, 3, 'ConBrecha'), ev(2, '2026-04-10', 7, 3, 'Cumple')]);
    $p2026 = periodo('2026-01-01', '2026-12-31');

    expect($c->cerradasEn($brechas, $p2026))->toBe(1)->and($c->detectadasEn($brechas, $p2026))->toBe(0);
});

it('cuenta las brechas activas al cierre con la DNF más reciente de cada colaborador a esa fecha', function () {
    $c = new CalculadorDeBrechasCerradas;
    $evaluaciones = [
        ev(1, '2026-01-10', 7, 3, 'ConBrecha'), ev(1, '2026-01-10', 7, 4, 'ConBrecha'), ev(1, '2026-01-10', 8, 3, 'ConBrecha'),
        ev(2, '2026-03-10', 7, 3, 'Cumple'), ev(2, '2026-03-10', 7, 4, 'ConBrecha'),
    ];

    expect($c->activasAl($evaluaciones, new DateTimeImmutable('2026-01-31')))->toBe(3)
        ->and($c->activasAl($evaluaciones, new DateTimeImmutable('2026-03-31')))->toBe(2)
        ->and($c->activasAl($evaluaciones, new DateTimeImmutable('2025-12-31')))->toBe(0);
});

it('genera la serie mensual de activas y cerradas', function () {
    $c = new CalculadorDeBrechasCerradas;
    $serie = $c->serieMensual([
        ev(1, '2026-01-10', 7, 3, 'ConBrecha'), ev(2, '2026-03-10', 7, 3, 'Cumple'),
    ], periodo('2026-01-01', '2026-04-30'));

    expect($serie)->toBe([
        ['mes' => '2026-01', 'activas' => 1, 'cerradas' => 0],
        ['mes' => '2026-02', 'activas' => 1, 'cerradas' => 0],
        ['mes' => '2026-03', 'activas' => 0, 'cerradas' => 1],
        ['mes' => '2026-04', 'activas' => 0, 'cerradas' => 0],
    ]);
});

it('calcula la evolución de competencias: primera DNF frente a la más reciente', function () {
    $c = new CalculadorDeBrechasCerradas;
    $filas = $c->evolucion([
        ev(1, '2026-01-10', 7, 3, 'ConBrecha', 'D', 'B'),
        ev(2, '2026-09-10', 7, 3, 'Cumple', 'B', 'B'),
        ev(1, '2026-01-10', 7, 4, 'ConBrecha', 'C', 'A'),
    ]);

    expect($filas)->toBe([
        ['competencia_id' => 3, 'nivel_primera' => 'D', 'nivel_actual' => 'B', 'nivel_requerido' => 'B', 'solo_actual' => false],
        ['competencia_id' => 4, 'nivel_primera' => null, 'nivel_actual' => 'C', 'nivel_requerido' => 'A', 'solo_actual' => true],
    ]);
});

it('la evolución ignora las evaluaciones sin nivel actual', function () {
    $c = new CalculadorDeBrechasCerradas;

    expect($c->evolucion([ev(1, '2026-01-10', 7, 3, 'SinEvaluacion', null)]))->toBe([]);
});
