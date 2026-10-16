<?php

use App\Modules\GestionPersonal\Domain\Services\NormalizadorDeTexto;
use App\Modules\GestionPersonal\Domain\Services\ReglasDeCategoria;

it('acepta un nombre de 100 caracteres y rechaza uno de 101 tras recortar', function () {
    $reglas = new ReglasDeCategoria;

    expect($reglas->errores($reglas->normalizar(['nombre' => str_repeat('a', 100)])))->toBe([])
        ->and($reglas->errores($reglas->normalizar(['nombre' => '  '.str_repeat('a', 100).'  '])))->toBe([])
        ->and($reglas->errores($reglas->normalizar(['nombre' => str_repeat('a', 101)])))->toHaveKey('nombre');
});

it('trata el nombre vacío o de solo espacios como ausente', function () {
    $reglas = new ReglasDeCategoria;

    expect($reglas->errores($reglas->normalizar(['nombre' => '   ']))['nombre'][0])->toBe('Este campo es obligatorio.')
        ->and($reglas->errores($reglas->normalizar(['nombre' => null])))->toHaveKey('nombre')
        ->and($reglas->errores([]))->toHaveKey('nombre');
});

it('valida el estado', function () {
    $reglas = new ReglasDeCategoria;

    expect($reglas->erroresEstado('Activa'))->toBe([])
        ->and($reglas->erroresEstado('Inactiva'))->toBe([])
        ->and($reglas->erroresEstado('Archivada'))->toHaveKey('estado')
        ->and($reglas->erroresEstado(null))->toHaveKey('estado')
        ->and($reglas->erroresEstado(['Activa']))->toHaveKey('estado');
});

it('la clave de nombre ignora mayúsculas y espacios exteriores pero conserva las tildes', function () {
    $n = new NormalizadorDeTexto;

    expect($n->claveDeNombre('  Contratos '))->toBe($n->claveDeNombre('contratos'))
        ->and($n->claveDeNombre('Educación'))->toBe($n->claveDeNombre('educación'))
        ->and($n->claveDeNombre('Educación'))->not->toBe($n->claveDeNombre('Educacion'))
        ->and($n->busquedaDeTexto('Educación'))->toBe($n->busquedaDeTexto('educacion'));
});

it('la clave normaliza a NFC', function () {
    $n = new NormalizadorDeTexto;
    $descompuesto = "Educacio\u{0301}n";

    expect($n->claveDeNombre($descompuesto))->toBe($n->claveDeNombre('Educación'));
});
