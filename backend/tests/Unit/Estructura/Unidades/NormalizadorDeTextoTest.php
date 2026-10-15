<?php

use App\Modules\Estructura\Domain\Services\NormalizadorDeTexto;

it('quita tildes y pasa a minúsculas', function () {
    $normalizador = new NormalizadorDeTexto;

    expect($normalizador->paraBuscar('Área'))->toBe('area')
        ->and($normalizador->paraBuscar('Sección'))->toBe('seccion')
        ->and($normalizador->paraBuscar('TECNOLOGÍA'))->toBe('tecnologia')
        ->and($normalizador->paraBuscar('Gestión de Proyectos'))->toBe('gestion de proyectos');
});

it('trata la ñ como n y recorta los espacios alrededor', function () {
    $normalizador = new NormalizadorDeTexto;

    expect($normalizador->paraBuscar('  Diseño  '))->toBe('diseno')
        ->and($normalizador->paraBuscar('UO-003'))->toBe('uo-003')
        ->and($normalizador->paraBuscar(''))->toBe('');
});
