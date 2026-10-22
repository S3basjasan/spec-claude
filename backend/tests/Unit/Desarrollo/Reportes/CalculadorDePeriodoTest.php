<?php

use App\Modules\Desarrollo\Domain\Exceptions\PeriodoInvalido;
use App\Modules\Desarrollo\Domain\Services\CalculadorDePeriodo;
use App\Modules\Desarrollo\Domain\ValueObjects\PeriodoReporte;

it('usa el año actual por defecto', function () {
    $p = (new CalculadorDePeriodo)->resolver(null, null, new DateTimeImmutable('2026-10-17'));

    expect($p->aArreglo())->toBe(['desde' => '2026-01-01', 'hasta' => '2026-12-31']);
});

it('completa con el año actual el extremo que falta', function () {
    $c = new CalculadorDePeriodo;
    $hoy = new DateTimeImmutable('2026-10-17');

    expect($c->resolver('2026-03-01', null, $hoy)->aArreglo())->toBe(['desde' => '2026-03-01', 'hasta' => '2026-12-31'])
        ->and($c->resolver(null, '2026-03-31', $hoy)->aArreglo())->toBe(['desde' => '2026-01-01', 'hasta' => '2026-03-31']);
});

it('rechaza un rango invertido o con formato inválido', function () {
    $c = new CalculadorDePeriodo;
    $hoy = new DateTimeImmutable('2026-10-17');

    expect(fn () => $c->resolver('2026-05-01', '2026-04-01', $hoy))->toThrow(PeriodoInvalido::class)
        ->and(fn () => $c->resolver('01/05/2026', '2026-06-01', $hoy))->toThrow(PeriodoInvalido::class)
        ->and(fn () => $c->resolver('2026-02-30', '2026-06-01', $hoy))->toThrow(PeriodoInvalido::class);
});

it('calcula el período anterior de igual duración inmediatamente previo', function (string $desde, string $hasta, string $antDesde, string $antHasta) {
    $p = new PeriodoReporte(new DateTimeImmutable($desde), new DateTimeImmutable($hasta));

    expect($p->anterior()->aArreglo())->toBe(['desde' => $antDesde, 'hasta' => $antHasta])
        ->and($p->anterior()->dias())->toBe($p->dias());
})->with([
    'año completo' => ['2026-01-01', '2026-12-31', '2025-01-01', '2025-12-31'],
    'duración igual aunque el año previo sea bisiesto' => ['2025-01-01', '2025-12-31', '2024-01-02', '2024-12-31'],
    '30 días' => ['2026-04-01', '2026-04-30', '2026-03-02', '2026-03-31'],
    'un solo día' => ['2026-04-10', '2026-04-10', '2026-04-09', '2026-04-09'],
]);

it('incluye ambos extremos del período', function () {
    $c = new CalculadorDePeriodo;
    $p = new PeriodoReporte(new DateTimeImmutable('2026-03-01'), new DateTimeImmutable('2026-03-31'));

    expect($c->pertenece($p, new DateTimeImmutable('2026-03-01 23:59:00')))->toBeTrue()
        ->and($c->pertenece($p, new DateTimeImmutable('2026-03-31 08:00:00')))->toBeTrue()
        ->and($c->pertenece($p, new DateTimeImmutable('2026-02-28')))->toBeFalse()
        ->and($c->pertenece($p, new DateTimeImmutable('2026-04-01')))->toBeFalse();
});

it('calcula la variación respecto al período anterior', function () {
    $v = (new CalculadorDePeriodo)->variacion(75.0, 50.0);

    expect($v->diferencia)->toBe(25.0)->and($v->variacionPct)->toBe(50.0);
});

it('no compara si falta el valor anterior o el actual', function () {
    $c = new CalculadorDePeriodo;

    expect($c->variacion(10.0, null)->diferencia)->toBeNull()
        ->and($c->variacion(10.0, null)->variacionPct)->toBeNull()
        ->and($c->variacion(null, 10.0)->diferencia)->toBeNull();
});

it('con anterior igual a cero informa la diferencia pero no el porcentaje', function () {
    $v = (new CalculadorDePeriodo)->variacion(4.0, 0.0);

    expect($v->diferencia)->toBe(4.0)->and($v->variacionPct)->toBeNull();
});

it('admite variaciones negativas', function () {
    $v = (new CalculadorDePeriodo)->variacion(3.5, 4.0);

    expect($v->diferencia)->toBe(-0.5)->and($v->variacionPct)->toBe(-12.5);
});
