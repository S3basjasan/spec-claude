<?php

use App\Modules\GestionPersonal\Domain\Services\ReglasDeFicha;

function estadoValido(array $cambios = []): array
{
    return array_replace([
        'tipo_identificacion' => 'Cedula',
        'numero_identificacion' => '0102030405',
        'nombres' => 'Ana',
        'apellidos' => 'Núñez',
        'fecha_nacimiento' => null,
        'numero_hijos' => null,
        'emergencia_nombre' => null,
        'emergencia_relacion' => null,
        'emergencia_telefono' => null,
    ], $cambios);
}

it('acepta una ficha mínima', function () {
    expect((new ReglasDeFicha)->errores(estadoValido(), '2026-10-15'))->toBe([]);
});

it('trata el texto de solo espacios como vacío', function () {
    $reglas = new ReglasDeFicha;
    $estado = $reglas->normalizar(estadoValido(['nombres' => '   ']));

    expect($reglas->errores($estado, '2026-10-15'))->toHaveKey('nombres');
});

it('la fecha de nacimiento de hoy es válida y la de mañana no', function () {
    $reglas = new ReglasDeFicha;

    expect($reglas->errores(estadoValido(['fecha_nacimiento' => '2026-10-15']), '2026-10-15'))->toBe([])
        ->and($reglas->errores(estadoValido(['fecha_nacimiento' => '2026-10-16']), '2026-10-15'))->toHaveKey('fecha_nacimiento');
});

it('el número de hijos 0 es válido y -1 no', function () {
    $reglas = new ReglasDeFicha;

    expect($reglas->errores(estadoValido(['numero_hijos' => 0]), '2026-10-15'))->toBe([])
        ->and($reglas->errores(estadoValido(['numero_hijos' => -1]), '2026-10-15'))->toHaveKey('numero_hijos');
});

it('el contacto de emergencia exige nombre y teléfono juntos o los tres vacíos', function () {
    $reglas = new ReglasDeFicha;

    expect($reglas->errores(estadoValido(['emergencia_relacion' => 'Madre']), '2026-10-15'))
        ->toHaveKeys(['emergencia_nombre', 'emergencia_telefono'])
        ->and($reglas->errores(estadoValido(['emergencia_nombre' => 'Rosa']), '2026-10-15'))
        ->toHaveKey('emergencia_telefono')
        ->and($reglas->errores(estadoValido(['emergencia_nombre' => 'Rosa', 'emergencia_telefono' => '0999999999']), '2026-10-15'))
        ->toBe([]);
});

it('conserva 0 al normalizar', function () {
    expect((new ReglasDeFicha)->normalizar(['numero_hijos' => 0, 'genero' => ' '])['numero_hijos'])->toBe(0);
});
