<?php

use App\Modules\Estructura\Domain\Services\ComparadorDeCambiosDeUnidad;
use App\Modules\Estructura\Domain\ValueObjects\EstadoUnidad;
use App\Modules\Estructura\Domain\ValueObjects\InstantaneaDeUnidad;

function instantaneaDe(string $nombre = 'Ventas', int $tipo = 2, ?int $padre = 1, EstadoUnidad $estado = EstadoUnidad::Activa, ?string $fecha = null): InstantaneaDeUnidad
{
    return new InstantaneaDeUnidad($nombre, $tipo, $padre, $estado, $fecha);
}

function resumen(array $cambios): array
{
    return array_map(fn ($c) => [$c->campo, $c->valorAnterior, $c->valorNuevo], $cambios);
}

it('al crear registra cada dato inicial con valor y valor anterior nulo', function () {
    $cambios = (new ComparadorDeCambiosDeUnidad)->comparar(null, instantaneaDe());

    expect(resumen($cambios))->toBe([
        ['nombre', null, 'Ventas'],
        ['tipo_unidad_id', null, '2'],
        ['unidad_padre_id', null, '1'],
        ['estado', null, 'Activa'],
    ]);
});

it('al crear una raíz no registra la unidad padre ni la fecha de supresión', function () {
    $cambios = (new ComparadorDeCambiosDeUnidad)->comparar(null, instantaneaDe('Gerencia general', 1, null));

    expect(array_column(resumen($cambios), 0))->toBe(['nombre', 'tipo_unidad_id', 'estado']);
});

it('registra el cambio de nombre y de tipo', function () {
    $cambios = (new ComparadorDeCambiosDeUnidad)->comparar(instantaneaDe(), instantaneaDe('Comercial', 3));

    expect(resumen($cambios))->toBe([['nombre', 'Ventas', 'Comercial'], ['tipo_unidad_id', '2', '3']]);
});

it('registra las reubicaciones con los ids anterior y nuevo, también desde y hacia la raíz', function () {
    $comparador = new ComparadorDeCambiosDeUnidad;

    expect(resumen($comparador->comparar(instantaneaDe(padre: 1), instantaneaDe(padre: 6))))->toBe([['unidad_padre_id', '1', '6']])
        ->and(resumen($comparador->comparar(instantaneaDe(padre: null), instantaneaDe(padre: 6))))->toBe([['unidad_padre_id', null, '6']])
        ->and(resumen($comparador->comparar(instantaneaDe(padre: 6), instantaneaDe(padre: null))))->toBe([['unidad_padre_id', '6', null]]);
});

it('desactivar registra el estado y la fecha de supresión; reactivar, lo inverso', function () {
    $comparador = new ComparadorDeCambiosDeUnidad;
    $activa = instantaneaDe();
    $inactiva = instantaneaDe(estado: EstadoUnidad::Inactiva, fecha: '2026-10-14');

    expect(resumen($comparador->comparar($activa, $inactiva)))->toBe([
        ['estado', 'Activa', 'Inactiva'],
        ['fecha_supresion', null, '2026-10-14'],
    ])->and(resumen($comparador->comparar($inactiva, $activa)))->toBe([
        ['estado', 'Inactiva', 'Activa'],
        ['fecha_supresion', '2026-10-14', null],
    ]);
});

it('sin cambios devuelve una lista vacía', function () {
    expect((new ComparadorDeCambiosDeUnidad)->comparar(instantaneaDe(), instantaneaDe()))->toBe([]);
});

it('con varios campos a la vez registra una fila por campo', function () {
    $cambios = (new ComparadorDeCambiosDeUnidad)->comparar(instantaneaDe(), instantaneaDe('Otra', 4, null));

    expect(array_column(resumen($cambios), 0))->toBe(['nombre', 'tipo_unidad_id', 'unidad_padre_id']);
});
