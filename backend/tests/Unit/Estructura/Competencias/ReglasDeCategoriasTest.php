<?php

use App\Modules\Estructura\Domain\Exceptions\CategoriasInvalidas;
use App\Modules\Estructura\Domain\Services\ReglasDeCategorias;

it('acepta cuatro nombres válidos y distintos', function () {
    ReglasDeCategorias::validar(['Maestría', 'Avanzado', 'Competente', 'Inicial']);

    expect(true)->toBeTrue();
});

it('acepta un nombre de exactamente 20 caracteres', function () {
    ReglasDeCategorias::validar([str_repeat('x', 20), 'B', 'C', 'D']);

    expect(true)->toBeTrue();
});

it('rechaza nombres vacíos, largos, repetidos o menos de cuatro', function (array $nombres, string $mensaje) {
    try {
        ReglasDeCategorias::validar($nombres);
        $this->fail('Debió lanzar CategoriasInvalidas.');
    } catch (CategoriasInvalidas $e) {
        expect($e->getMessage())->toBe($mensaje);
    }
})->with([
    'vacío' => [['A', 'B', 'C', '  '], 'Escribe las cuatro categorías, sin repetir nombres.'],
    'solo tres' => [['A', 'B', 'C'], 'Escribe las cuatro categorías, sin repetir nombres.'],
    '21 caracteres' => [[str_repeat('x', 21), 'B', 'C', 'D'], 'Un nombre de categoría no puede superar los 20 caracteres.'],
    'mayúsculas y espacios' => [['Buena', ' buena ', 'C', 'D'], 'Escribe las cuatro categorías, sin repetir nombres.'],
    'con y sin tilde' => [['Maestría', 'Maestria', 'C', 'D'], 'Escribe las cuatro categorías, sin repetir nombres.'],
]);

it('calcula la clave sin tildes, en minúsculas y recortada', function () {
    expect(ReglasDeCategorias::clave('  MAESTRÍA '))->toBe('maestria');
});
