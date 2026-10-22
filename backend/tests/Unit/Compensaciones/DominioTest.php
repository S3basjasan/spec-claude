<?php

use App\Modules\Compensaciones\Domain\Entities\Categoria;
use App\Modules\Compensaciones\Domain\Exceptions\DatosDeCategoriaInvalidos;
use App\Modules\Compensaciones\Domain\ValueObjects\EstadoCategoria;
use App\Modules\Compensaciones\Domain\ValueObjects\Icono;
use App\Modules\Compensaciones\Domain\ValueObjects\NombreCategoria;
use App\Modules\Compensaciones\Domain\ValueObjects\Normalizador;
use App\Modules\Compensaciones\Domain\ValueObjects\PuntuacionBase;

it('normaliza sin distinguir mayúsculas, tildes ni espacios', function () {
    expect(Normalizador::normalizar('  Orientación   al CLIENTE '))->toBe('orientacion al cliente')
        ->and(Normalizador::normalizar('INNOVACIÓN'))->toBe(Normalizador::normalizar('innovacion'));
});

it('detecta etiquetas HTML', function () {
    expect(Normalizador::contieneHtml('hola <b>mundo</b>'))->toBeTrue()
        ->and(Normalizador::contieneHtml('<script>alert(1)</script>'))->toBeTrue()
        ->and(Normalizador::contieneHtml('menor que 3 < 5 y mayor que 2 > 1'))->toBeFalse();
});

it('acepta puntuaciones de 1 a 1000 y rechaza el resto', function (mixed $valor) {
    expect(fn () => new PuntuacionBase($valor))->toThrow(DatosDeCategoriaInvalidos::class);
})->with([0, -5, 1001, 10.5, 'abc', null, '12.5']);

it('acepta los límites de la puntuación', function () {
    expect((new PuntuacionBase(1))->valor)->toBe(1)
        ->and((new PuntuacionBase(1000))->valor)->toBe(1000)
        ->and((new PuntuacionBase('250'))->valor)->toBe(250);
});

it('valida el largo del nombre tras recortar espacios', function () {
    expect(fn () => new NombreCategoria('  ab  '))->toThrow(DatosDeCategoriaInvalidos::class)
        ->and((new NombreCategoria('  abc  '))->valor)->toBe('abc')
        ->and((new NombreCategoria(str_repeat('a', 100)))->valor)->toHaveLength(100)
        ->and(fn () => new NombreCategoria(str_repeat('a', 101)))->toThrow(DatosDeCategoriaInvalidos::class);
});

it('tiene exactamente doce íconos y rechaza otros', function () {
    expect(Icono::valores())->toHaveCount(12)
        ->and(Icono::tryFrom('estrella'))->toBe(Icono::Estrella)
        ->and(Icono::tryFrom('cohete'))->toBeNull();
});

it('asigna el orden de los indicadores desde 1 y exige de 1 a 5', function () {
    $indicadores = Categoria::indicadoresDesde(['Primer indicador', 'Segundo indicador']);

    expect($indicadores[0]->orden)->toBe(1)
        ->and($indicadores[1]->orden)->toBe(2)
        ->and(fn () => Categoria::indicadoresDesde([]))->toThrow(DatosDeCategoriaInvalidos::class)
        ->and(fn () => Categoria::indicadoresDesde(array_fill(0, 6, 'Indicador')))->toThrow(DatosDeCategoriaInvalidos::class);
});

it('rechaza indicadores repetidos por su forma normalizada', function () {
    expect(fn () => Categoria::indicadoresDesde(['Trabaja en equipo', '  TRABAJA en equipo ']))
        ->toThrow(DatosDeCategoriaInvalidos::class);
});

it('es seleccionable solo si está activa y su competencia está activa', function () {
    $activa = Categoria::propuesta('Innovación', 'Propone ideas nuevas para mejorar.', 1, 100, 'bombilla', ['Propone mejoras al equipo']);
    $inactiva = Categoria::propuesta('Innovación', 'Propone ideas nuevas para mejorar.', 1, 100, 'bombilla', ['Propone mejoras al equipo'], EstadoCategoria::Inactivo);

    expect($activa->esSeleccionable(true))->toBeTrue()
        ->and($activa->esSeleccionable(false))->toBeFalse()
        ->and($inactiva->esSeleccionable(true))->toBeFalse();
});

it('calcula las diferencias campo por campo', function () {
    $antes = Categoria::propuesta('Innovación', 'Propone ideas nuevas para mejorar.', 1, 100, 'bombilla', ['Propone mejoras al equipo']);
    $despues = Categoria::propuesta('Innovación', 'Propone ideas nuevas para mejorar.', 1, 150, 'bombilla', ['Propone mejoras al equipo', 'Prueba ideas pequeñas']);

    expect(array_keys($antes->diferenciasCon($despues)))->toBe(['puntuacion_base', 'indicadores'])
        ->and($antes->diferenciasCon($antes))->toBe([]);
});
