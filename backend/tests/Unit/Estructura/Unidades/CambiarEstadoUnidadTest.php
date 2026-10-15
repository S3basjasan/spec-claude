<?php

use App\Modules\Estructura\Application\UseCases\CambiarEstadoUnidad;
use App\Modules\Estructura\Domain\Exceptions\UnidadConDependenciasEnUso;
use App\Modules\Estructura\Domain\Exceptions\UnidadNoEncontrada;
use App\Modules\Estructura\Domain\Exceptions\UnidadNoReactivable;
use App\Modules\Estructura\Domain\ValueObjects\EstadoUnidad;
use Tests\Unit\Estructura\Unidades\NombresDeUsuariosEnMemoria;
use Tests\Unit\Estructura\Unidades\RepositorioUnidadesEnMemoria;

function casoDeEstado(RepositorioUnidadesEnMemoria $repo): CambiarEstadoUnidad
{
    return new CambiarEstadoUnidad($repo, new NombresDeUsuariosEnMemoria);
}

it('desactiva una hoja sin posiciones y fija la fecha de supresión', function () {
    $repo = new RepositorioUnidadesEnMemoria;
    $raiz = $repo->agregar('Raíz', null, 1);
    $hoja = $repo->agregar('Hoja', $raiz, 2);

    $dto = casoDeEstado($repo)->ejecutar($hoja, EstadoUnidad::Inactiva, 1);

    expect($dto->estado)->toBe('Inactiva')->and($dto->fechaSupresion)->not->toBeNull()
        ->and(array_column($repo->historial, 'campo'))->toBe(['estado', 'fecha_supresion']);
});

it('rechaza desactivar con dependientes activas o posiciones vigentes con las cantidades', function () {
    $repo = new RepositorioUnidadesEnMemoria;
    $raiz = $repo->agregar('Raíz', null, 1);
    $a = $repo->agregar('A', $raiz, 2);
    $repo->agregar('A1', $a, 3);
    $repo->posicionesVigentes[$a] = 2;

    try {
        casoDeEstado($repo)->ejecutar($a, EstadoUnidad::Inactiva);
        $this->fail('Debió lanzar UnidadConDependenciasEnUso.');
    } catch (UnidadConDependenciasEnUso $e) {
        expect($e->dependientesActivas)->toBe(1)->and($e->posicionesVigentes)->toBe(2);
    }
});

it('las dependientes inactivas no impiden desactivar', function () {
    $repo = new RepositorioUnidadesEnMemoria;
    $raiz = $repo->agregar('Raíz', null, 1);
    $a = $repo->agregar('A', $raiz, 2);
    $repo->agregar('A1', $a, 3, EstadoUnidad::Inactiva);

    expect(casoDeEstado($repo)->ejecutar($a, EstadoUnidad::Inactiva)->estado)->toBe('Inactiva');
});

it('reactiva con padre activo y rechaza con padre inactivo o con otra raíz activa', function () {
    $repo = new RepositorioUnidadesEnMemoria;
    $raiz = $repo->agregar('Raíz', null, 1);
    $inactivaConPadreActivo = $repo->agregar('A', $raiz, 2, EstadoUnidad::Inactiva);
    $padreInactivo = $repo->agregar('P', $raiz, 2, EstadoUnidad::Inactiva);
    $hija = $repo->agregar('H', $padreInactivo, 3, EstadoUnidad::Inactiva);
    $otraRaiz = $repo->agregar('Otra raíz', null, 1, EstadoUnidad::Inactiva);
    $caso = casoDeEstado($repo);

    expect($caso->ejecutar($inactivaConPadreActivo, EstadoUnidad::Activa)->estado)->toBe('Activa');

    foreach ([[$hija, UnidadNoReactivable::PADRE_INACTIVO], [$otraRaiz, UnidadNoReactivable::RAIZ_ACTIVA_EXISTENTE]] as [$id, $motivo]) {
        try {
            $caso->ejecutar($id, EstadoUnidad::Activa);
            $this->fail('Debió lanzar UnidadNoReactivable.');
        } catch (UnidadNoReactivable $e) {
            expect($e->motivo)->toBe($motivo);
        }
    }
});

it('pedir el estado actual no cambia nada y una unidad inexistente falla', function () {
    $repo = new RepositorioUnidadesEnMemoria;
    $raiz = $repo->agregar('Raíz', null, 1);

    casoDeEstado($repo)->ejecutar($raiz, EstadoUnidad::Activa);

    expect($repo->escrituras)->toBe(0)->and($repo->historial)->toBe([])
        ->and(fn () => casoDeEstado($repo)->ejecutar(99, EstadoUnidad::Inactiva))->toThrow(UnidadNoEncontrada::class);
});
