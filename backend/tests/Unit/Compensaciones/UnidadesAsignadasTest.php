<?php

use App\Modules\Compensaciones\Domain\Exceptions\DatosDeCategoriaInvalidos;
use App\Modules\Compensaciones\Domain\ValueObjects\UnidadesAsignadas;

it('ordena y quita repetidos', function () {
    $unidades = new UnidadesAsignadas([3, 1, 3, 2]);

    expect($unidades->ids())->toBe([1, 2, 3])
        ->and($unidades->comoJson())->toBe('[1,2,3]')
        ->and($unidades->contiene(2))->toBeTrue()
        ->and($unidades->contiene(9))->toBeFalse();
});

it('acepta ids numéricos como texto', function () {
    expect((new UnidadesAsignadas(['2', 1]))->ids())->toBe([1, 2]);
});

it('exige al menos una unidad', function () {
    new UnidadesAsignadas([]);
})->throws(DatosDeCategoriaInvalidos::class, 'Elige al menos una unidad.');

it('rechaza ids que no son enteros positivos', function (mixed $invalido) {
    new UnidadesAsignadas([1, $invalido]);
})->with([[0], [-1], ['abc'], [1.5], [null]])->throws(DatosDeCategoriaInvalidos::class);
