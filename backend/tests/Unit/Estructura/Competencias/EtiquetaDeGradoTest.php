<?php

use App\Modules\Estructura\Domain\ValueObjects\EscalaDeGrados;
use App\Modules\Estructura\Domain\ValueObjects\GradoLetra;

function escalaDe(string $nombre, array $valores): EscalaDeGrados
{
    return new EscalaDeGrados(1, $nombre, false, $valores);
}

it('en Letras la etiqueta es "Grado" más la letra', function () {
    $escala = escalaDe('Letras', [1 => 'A', 2 => 'B', 3 => 'C', 4 => 'D']);

    expect([1, 2, 3, 4])->each->toBeInt()
        ->and(array_map(fn (int $o) => $escala->etiqueta($o), [1, 2, 3, 4]))->toBe(['Grado A', 'Grado B', 'Grado C', 'Grado D']);
});

it('en Números la etiqueta es el valor del catálogo', function () {
    $escala = escalaDe('Números', [1 => 'Nivel 1', 2 => 'Nivel 2', 3 => 'Nivel 3', 4 => 'Nivel 4']);

    expect($escala->etiqueta(3))->toBe('Nivel 3');
});

it('en Categorías usa el nombre propio y, sin él, el valor por defecto', function () {
    $escala = escalaDe('Categorías', [1 => 'Excelente', 2 => 'Muy buena', 3 => 'Buena', 4 => 'Normal']);

    expect($escala->etiqueta(1, [1 => 'Maestría', 2 => 'Avanzado', 3 => 'Competente', 4 => 'Inicial']))->toBe('Maestría')
        ->and($escala->etiqueta(2))->toBe('Muy buena')
        ->and($escala->etiqueta(2, null))->toBe('Muy buena');
});

it('los nombres propios solo se aplican a la escala Categorías', function () {
    $escala = escalaDe('Números', [1 => 'Nivel 1', 2 => 'Nivel 2', 3 => 'Nivel 3', 4 => 'Nivel 4']);

    expect($escala->etiqueta(1, [1 => 'Maestría']))->toBe('Nivel 1');
});

it('la clave posicional A–D sale del orden y es independiente de la escala', function () {
    expect(GradoLetra::desdeOrden(1))->toBe('A')
        ->and(GradoLetra::desdeOrden(4))->toBe('D')
        ->and(GradoLetra::aOrden('C'))->toBe(3);
});
