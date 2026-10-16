<?php

use App\Modules\GestionPersonal\Domain\Exceptions\ArchivoInvalido;
use App\Modules\GestionPersonal\Domain\Services\ReglasDeDocumento;

function datosDocumento(array $cambios = []): array
{
    return array_replace(['titulo' => 'Contrato', 'categoria_id' => 3, 'visible_colaborador' => false], $cambios);
}

it('acepta un documento válido y recorta el título', function () {
    $reglas = new ReglasDeDocumento;

    expect($reglas->errores($reglas->normalizar(datosDocumento(['titulo' => '  Contrato  ']))))->toBe([])
        ->and($reglas->normalizar(['titulo' => '  Contrato  '])['titulo'])->toBe('Contrato');
});

it('el título es obligatorio y admite 200 pero no 201 caracteres', function () {
    $reglas = new ReglasDeDocumento;

    expect($reglas->errores($reglas->normalizar(datosDocumento(['titulo' => '   ']))))->toHaveKey('titulo')
        ->and($reglas->errores($reglas->normalizar(datosDocumento(['titulo' => null]))))->toHaveKey('titulo')
        ->and($reglas->errores($reglas->normalizar(datosDocumento(['titulo' => str_repeat('a', 200)]))))->toBe([])
        ->and($reglas->errores($reglas->normalizar(datosDocumento(['titulo' => str_repeat('a', 201)]))))->toHaveKey('titulo');
});

it('la categoría es obligatoria y la visibilidad debe ser booleana', function () {
    $reglas = new ReglasDeDocumento;

    expect($reglas->errores(datosDocumento(['categoria_id' => null])))->toHaveKey('categoria_id')
        ->and($reglas->errores(datosDocumento(['categoria_id' => 0])))->toHaveKey('categoria_id')
        ->and($reglas->errores(datosDocumento(['visible_colaborador' => 'si'])))->toHaveKey('visible_colaborador');
});

it('el tamaño admite 1 y 10000000 bytes pero rechaza 0 y 10000001', function () {
    $reglas = new ReglasDeDocumento;
    $reglas->validarTamano(1);
    $reglas->validarTamano(10000000);

    expect(fn () => $reglas->validarTamano(0))->toThrow(ArchivoInvalido::class, 'vacío')
        ->and(fn () => $reglas->validarTamano(10000001))->toThrow(ArchivoInvalido::class, '10 MB');
});

it('la huella de operación cambia con el archivo, el actor, la ficha o los campos', function () {
    $reglas = new ReglasDeDocumento;
    $base = $reglas->hashDeOperacion(1, 2, 'Contrato', 3, false, str_repeat('a', 64));

    expect($base)->toBe($reglas->hashDeOperacion(1, 2, 'Contrato', 3, false, str_repeat('a', 64)))
        ->and($base)->not->toBe($reglas->hashDeOperacion(1, 2, 'Contrato', 3, false, str_repeat('b', 64)))
        ->and($base)->not->toBe($reglas->hashDeOperacion(1, 9, 'Contrato', 3, false, str_repeat('a', 64)))
        ->and($base)->not->toBe($reglas->hashDeOperacion(9, 2, 'Contrato', 3, false, str_repeat('a', 64)))
        ->and($base)->not->toBe($reglas->hashDeOperacion(1, 2, 'Otro', 3, false, str_repeat('a', 64)))
        ->and($base)->not->toBe($reglas->hashDeOperacion(1, 2, 'Contrato', 4, false, str_repeat('a', 64)))
        ->and($base)->not->toBe($reglas->hashDeOperacion(1, 2, 'Contrato', 3, true, str_repeat('a', 64)));
});
