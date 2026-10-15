<?php

use App\Modules\Estructura\Application\UseCases\ActualizarUnidad;
use App\Modules\Estructura\Domain\Exceptions\UnidadInvalida;
use App\Modules\Estructura\Domain\Exceptions\UnidadModificada;
use App\Modules\Estructura\Domain\Exceptions\UnidadNoEncontrada;
use App\Modules\Estructura\Domain\ValueObjects\DatosUnidad;
use App\Modules\Estructura\Domain\ValueObjects\EstadoUnidad;
use Tests\Unit\Estructura\Unidades\NombresDeUsuariosEnMemoria;
use Tests\Unit\Estructura\Unidades\RepositorioUnidadesEnMemoria;

function repositorioParaActualizar(): RepositorioUnidadesEnMemoria
{
    $repo = new RepositorioUnidadesEnMemoria;
    $raiz = $repo->agregar('Raíz', null, 1);
    $a = $repo->agregar('A', $raiz, 2);
    $repo->agregar('B', $raiz, 2);
    $repo->agregar('A1', $a, 3);

    return $repo;
}

it('actualiza recortando el nombre y sube la versión', function () {
    $repo = repositorioParaActualizar();
    $caso = new ActualizarUnidad($repo, new NombresDeUsuariosEnMemoria);

    $dto = $caso->ejecutar(2, 1, new DatosUnidad('  Alfa  ', 2, 1), null, 2);

    expect($dto->nombre)->toBe('Alfa')->and($dto->version)->toBe(2)->and($repo->ultimoActor)->toBe(2);
});

it('sin cambios no escribe ni cambia la versión', function () {
    $repo = repositorioParaActualizar();
    $caso = new ActualizarUnidad($repo, new NombresDeUsuariosEnMemoria);

    $dto = $caso->ejecutar(2, 1, new DatosUnidad(' A ', 2, 1));

    expect($dto->version)->toBe(1)->and($repo->escrituras)->toBe(0)->and($repo->historial)->toBe([]);
});

it('propaga versión desactualizada, unidad inexistente y código distinto', function () {
    $repo = repositorioParaActualizar();
    $caso = new ActualizarUnidad($repo, new NombresDeUsuariosEnMemoria);
    $caso->ejecutar(2, 1, new DatosUnidad('Alfa', 2, 1));

    expect(fn () => $caso->ejecutar(2, 1, new DatosUnidad('Otra', 2, 1)))->toThrow(UnidadModificada::class)
        ->and(fn () => $caso->ejecutar(99, 1, new DatosUnidad('X', 2, 1)))->toThrow(UnidadNoEncontrada::class)
        ->and(fn () => $caso->ejecutar(3, 1, new DatosUnidad('B', 2, 1), 'UO-999'))->toThrow(UnidadInvalida::class);
});

it('rechaza como padre la propia unidad, una dependiente o una inactiva', function () {
    $repo = repositorioParaActualizar();
    $inactiva = $repo->agregar('Retirada', 1, 2, EstadoUnidad::Inactiva);
    $caso = new ActualizarUnidad($repo, new NombresDeUsuariosEnMemoria);

    expect(fn () => $caso->ejecutar(2, 1, new DatosUnidad('A', 2, 2)))->toThrow(UnidadInvalida::class)
        ->and(fn () => $caso->ejecutar(2, 1, new DatosUnidad('A', 2, 4)))->toThrow(UnidadInvalida::class)
        ->and(fn () => $caso->ejecutar(3, 1, new DatosUnidad('B', 2, $inactiva)))->toThrow(UnidadInvalida::class)
        ->and(fn () => $caso->ejecutar(3, 1, new DatosUnidad('B', 2, null)))->toThrow(UnidadInvalida::class);
});

it('reubicar mueve la rama: la hija sigue colgando de su unidad', function () {
    $repo = repositorioParaActualizar();
    $caso = new ActualizarUnidad($repo, new NombresDeUsuariosEnMemoria);

    $caso->ejecutar(2, 1, new DatosUnidad('A', 2, 3));

    $a1 = $repo->obtener(4);
    expect($a1->padre->nombre)->toBe('A')->and($a1->nivel)->toBe(3)
        ->and(array_column(array_map(fn ($c) => (array) $c, $repo->historial), 'campo'))->toBe(['unidad_padre_id']);
});
