<?php

use App\Modules\Estructura\Application\UseCases\CrearUnidad;
use App\Modules\Estructura\Application\UseCases\ListarOpcionesDeUnidad;
use App\Modules\Estructura\Domain\Exceptions\UnidadInvalida;
use App\Modules\Estructura\Domain\ValueObjects\DatosUnidad;
use App\Modules\Estructura\Domain\ValueObjects\EstadoUnidad;
use Tests\Unit\Estructura\Unidades\NombresDeUsuariosEnMemoria;
use Tests\Unit\Estructura\Unidades\RepositorioUnidadesEnMemoria;

it('crea la unidad recortando el nombre y devuelve su detalle con auditoría', function () {
    $repo = new RepositorioUnidadesEnMemoria;
    $caso = new CrearUnidad($repo, new NombresDeUsuariosEnMemoria);

    $dto = $caso->ejecutar(new DatosUnidad('  Gerencia general  ', 1, null), 1);

    expect($dto->nombre)->toBe('Gerencia general')
        ->and($dto->codigo)->toBe('UO-001')
        ->and($dto->estado)->toBe('Activa')
        ->and($dto->version)->toBe(1)
        ->and($dto->auditoria['created_by'])->toBe(['id' => 1, 'nombre' => 'Ana'])
        ->and($repo->historial)->toHaveCount(3);
});

it('propaga tipo inexistente, padre inexistente, padre inactivo y raíz duplicada', function () {
    $repo = new RepositorioUnidadesEnMemoria;
    $raiz = $repo->agregar('Raíz', null, 1);
    $inactiva = $repo->agregar('Retirada', $raiz, 2, EstadoUnidad::Inactiva);
    $caso = new CrearUnidad($repo, new NombresDeUsuariosEnMemoria);

    expect(fn () => $caso->ejecutar(new DatosUnidad('A', 99, $raiz)))->toThrow(UnidadInvalida::class)
        ->and(fn () => $caso->ejecutar(new DatosUnidad('A', 2, 99)))->toThrow(UnidadInvalida::class)
        ->and(fn () => $caso->ejecutar(new DatosUnidad('A', 2, $inactiva)))->toThrow(UnidadInvalida::class)
        ->and(fn () => $caso->ejecutar(new DatosUnidad('A', 2, null)))->toThrow(UnidadInvalida::class)
        ->and(count($repo->filas))->toBe(2);
});

it('lista las opciones: tipos, unidades activas candidatas y si hay otra raíz activa', function () {
    $repo = new RepositorioUnidadesEnMemoria;
    $raiz = $repo->agregar('Raíz', null, 1);
    $hija = $repo->agregar('Hija', $raiz, 2);
    $repo->agregar('Nieta', $hija, 3);
    $repo->agregar('Retirada', $raiz, 2, EstadoUnidad::Inactiva);
    $caso = new ListarOpcionesDeUnidad($repo);

    $sin = $caso->ejecutar();
    $conExcluir = $caso->ejecutar($hija);

    expect(array_column($sin->unidades, 'nombre'))->toBe(['Raíz', 'Hija', 'Nieta'])
        ->and(array_column($conExcluir->unidades, 'nombre'))->toBe(['Raíz'])
        ->and(count($sin->tipos))->toBe(4)
        ->and($sin->hayOtraRaizActiva)->toBeTrue()
        ->and($caso->ejecutar($raiz)->hayOtraRaizActiva)->toBeFalse();
});
