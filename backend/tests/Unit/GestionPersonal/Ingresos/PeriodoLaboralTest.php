<?php

use App\Modules\GestionPersonal\Domain\Services\PeriodoLaboral;

it('dos periodos contiguos no se superponen: el inicio se incluye y el fin se excluye', function () {
    $anterior = new PeriodoLaboral('2026-01-01', '2026-06-30');
    $nuevo = new PeriodoLaboral('2026-06-30');

    expect($anterior->superpone($nuevo))->toBeFalse()
        ->and($nuevo->superpone($anterior))->toBeFalse()
        ->and($anterior->contiene('2026-06-29'))->toBeTrue()
        ->and($anterior->contiene('2026-06-30'))->toBeFalse();
});

it('un periodo abierto se superpone con cualquiera que no haya terminado antes de su inicio', function () {
    $abierto = new PeriodoLaboral('2026-03-01');

    expect($abierto->superpone(new PeriodoLaboral('2026-01-01', '2026-03-02')))->toBeTrue()
        ->and($abierto->superpone(new PeriodoLaboral('2026-01-01', '2026-03-01')))->toBeFalse()
        ->and($abierto->superpone(new PeriodoLaboral('2030-01-01', '2031-01-01')))->toBeTrue()
        ->and($abierto->superpone(new PeriodoLaboral('2026-02-01')))->toBeTrue();
});

it('un periodo vacío no ocupa ni se superpone', function () {
    $vacio = new PeriodoLaboral('2026-05-01', '2026-05-01');

    expect($vacio->estaVacio())->toBeTrue()
        ->and($vacio->superpone(new PeriodoLaboral('2026-01-01')))->toBeFalse()
        ->and($vacio->contiene('2026-05-01'))->toBeFalse();
});

it('superposición total y parcial', function () {
    $a = new PeriodoLaboral('2026-01-01', '2026-12-31');

    expect($a->superpone(new PeriodoLaboral('2026-03-01', '2026-04-01')))->toBeTrue()
        ->and($a->superpone(new PeriodoLaboral('2025-06-01', '2026-02-01')))->toBeTrue()
        ->and($a->superpone(new PeriodoLaboral('2027-01-01', '2027-02-01')))->toBeFalse();
});

it('detecta si terminó efectivamente en o antes de una fecha', function () {
    expect((new PeriodoLaboral('2026-01-01', '2026-06-01'))->terminoEnOAntesDe('2026-06-01'))->toBeTrue()
        ->and((new PeriodoLaboral('2026-01-01', '2026-06-02'))->terminoEnOAntesDe('2026-06-01'))->toBeFalse()
        ->and((new PeriodoLaboral('2026-01-01'))->terminoEnOAntesDe('2999-01-01'))->toBeFalse();
});
