<?php

use App\Modules\Estructura\Domain\Services\ArbolDeUnidades;
use App\Modules\Estructura\Domain\ValueObjects\EstadoUnidad;
use App\Modules\Estructura\Domain\ValueObjects\NodoDeUnidad;
use App\Modules\Estructura\Domain\ValueObjects\TipoDeUnidad;

function nodoDe(int $id, ?int $padre, ?string $codigo = null, EstadoUnidad $estado = EstadoUnidad::Activa): NodoDeUnidad
{
    return new NodoDeUnidad($id, $codigo ?? sprintf('UO-%03d', $id), "Unidad $id", new TipoDeUnidad(1, 'Gerencia'), $padre, $estado);
}

it('ordena el árbol en profundidad con el nivel de cada unidad', function () {
    // 1 ── 2 ── 4
    //  │    └── 5
    //  └── 3
    $arbol = new ArbolDeUnidades([nodoDe(3, 1), nodoDe(5, 2), nodoDe(1, null), nodoDe(4, 2), nodoDe(2, 1)]);

    expect($arbol->ordenados())->toBe([1, 2, 4, 5, 3])
        ->and($arbol->nivelDe(1))->toBe(0)
        ->and($arbol->nivelDe(2))->toBe(1)
        ->and($arbol->nivelDe(4))->toBe(2)
        ->and($arbol->nivelDe(3))->toBe(1);
});

it('ordena los hermanos por el número del código y no alfabéticamente', function () {
    $arbol = new ArbolDeUnidades([
        nodoDe(1, null),
        nodoDe(2, 1, 'UO-10'),
        nodoDe(3, 1, 'UO-9'),
        nodoDe(4, 1, 'UO-1000'),
    ]);

    expect($arbol->ordenados())->toBe([1, 3, 2, 4]);
});

it('ordena varias raíces (una activa y otra inactiva) por código', function () {
    $arbol = new ArbolDeUnidades([nodoDe(2, null, 'UO-008', EstadoUnidad::Inactiva), nodoDe(1, null, 'UO-001'), nodoDe(3, 1)]);

    expect($arbol->ordenados())->toBe([1, 3, 2])
        ->and($arbol->nivelDe(2))->toBe(0);
});

it('encuentra los descendientes directos e indirectos y ninguno en una hoja', function () {
    $arbol = new ArbolDeUnidades([nodoDe(1, null), nodoDe(2, 1), nodoDe(3, 2), nodoDe(4, 3), nodoDe(5, 1)]);

    expect($arbol->descendientesDe(1))->toEqualCanonicalizing([2, 3, 4, 5])
        ->and($arbol->descendientesDe(2))->toEqualCanonicalizing([3, 4])
        ->and($arbol->descendientesDe(4))->toBe([]);
});

it('encuentra las ancestras de la más cercana a la raíz', function () {
    $arbol = new ArbolDeUnidades([nodoDe(1, null), nodoDe(2, 1), nodoDe(3, 2), nodoDe(4, 3)]);

    expect($arbol->ancestrasDe(4))->toBe([3, 2, 1])
        ->and($arbol->ancestrasDe(1))->toBe([]);
});

it('con un padre inexistente no cuelga ni pierde la unidad', function () {
    $arbol = new ArbolDeUnidades([nodoDe(1, null), nodoDe(2, 99)]);

    expect($arbol->ordenados())->toBe([1, 2])->and($arbol->nivelDe(2))->toBe(0);
});

it('con un ciclo no cuelga ni duplica filas', function () {
    $arbol = new ArbolDeUnidades([nodoDe(1, 2), nodoDe(2, 1), nodoDe(3, null)]);

    expect($arbol->ordenados())->toHaveCount(3)->and(array_unique($arbol->ordenados()))->toHaveCount(3)
        ->and($arbol->descendientesDe(1))->toBe([2])
        ->and($arbol->ancestrasDe(1))->toBe([2]);
});
