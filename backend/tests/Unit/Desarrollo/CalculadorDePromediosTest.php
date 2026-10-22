<?php

use App\Modules\Desarrollo\Domain\Services\CalculadorDePromedios;

it('promedia las calificaciones asignadas', function () {
    $c = new CalculadorDePromedios;

    expect($c->promedioDe([3, 4, 5]))->toBe(4.0)
        ->and($c->promedioDe([4, 5]))->toBe(4.5)
        ->and($c->promedio(9, 2))->toBe(4.5);
});

it('devuelve nulo cuando no hay calificaciones', function () {
    $c = new CalculadorDePromedios;

    expect($c->promedioDe([]))->toBeNull()
        ->and($c->promedio(0, 0))->toBeNull();
});

it('redondea a un decimal con la mitad hacia arriba', function (array $notas, float $esperado) {
    expect((new CalculadorDePromedios)->promedioDe($notas))->toBe($esperado);
})->with([
    '4,125 baja a 4,1' => [[4, 4, 4, 5, 4, 4, 4, 4], 4.1],
    '4,25 sube a 4,3' => [[4, 4, 4, 5], 4.3],
    '4,25 con doce notas también sube a 4,3' => [[4, 4, 4, 4, 4, 4, 4, 4, 4, 5, 5, 5], 4.3],
    '1,33 baja a 1,3' => [[1, 1, 2], 1.3],
]);
