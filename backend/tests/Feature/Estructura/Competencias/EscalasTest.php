<?php

it('devuelve las tres escalas con sus cuatro valores ordenados y la marca de la activa', function () {
    actuarComoUsuario();

    $escalas = $this->getJson('/api/tipo-grados')->assertOk()->json();

    expect(array_column($escalas, 'nombre'))->toBe(['Letras', 'Números', 'Categorías'])
        ->and(array_column($escalas, 'activo'))->toBe([true, false, false])
        ->and(array_column($escalas[0]['valores'], 'etiqueta'))->toBe(['Grado A', 'Grado B', 'Grado C', 'Grado D'])
        ->and(array_column($escalas[1]['valores'], 'etiqueta'))->toBe(['Nivel 1', 'Nivel 2', 'Nivel 3', 'Nivel 4'])
        ->and(array_column($escalas[2]['valores'], 'etiqueta'))->toBe(['Excelente', 'Muy buena', 'Buena', 'Normal'])
        ->and(array_column($escalas[2]['valores'], 'orden'))->toBe([1, 2, 3, 4]);
});

it('exige sesión para consultar las escalas', function () {
    $this->getJson('/api/tipo-grados')->assertUnauthorized();
});
