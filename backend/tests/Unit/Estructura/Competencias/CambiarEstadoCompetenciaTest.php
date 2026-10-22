<?php

use App\Modules\Estructura\Application\UseCases\AplicarEscala;
use App\Modules\Estructura\Application\UseCases\CambiarEstadoCompetencia;
use App\Modules\Estructura\Application\UseCases\CrearCompetencia;
use App\Modules\Estructura\Domain\Entities\Competencia;
use App\Modules\Estructura\Domain\Exceptions\CompetenciaIncompleta;
use App\Modules\Estructura\Domain\Exceptions\CompetenciaNoEncontrada;
use App\Modules\Estructura\Domain\Exceptions\EscalaNoEncontrada;
use App\Modules\Estructura\Domain\Exceptions\EscalaSinCambio;
use App\Modules\Estructura\Domain\Exceptions\EstadoSinCambio;
use App\Modules\Estructura\Domain\ValueObjects\EstadoCompetencia;
use App\Modules\Estructura\Domain\ValueObjects\TipoCompetencia;
use Tests\Unit\Estructura\Competencias\RepositorioEnMemoria;

require_once __DIR__.'/Ayudas.php';

function competenciaActiva(RepositorioEnMemoria $repo): int
{
    return (new CrearCompetencia($repo, validadorUnitario(), auditoriaUnitaria()))->ejecutar(datosUnitarios())->id;
}

it('desactiva y reactiva registrando el historial con el actor', function () {
    $repo = new RepositorioEnMemoria;
    $id = competenciaActiva($repo);
    $caso = new CambiarEstadoCompetencia($repo, auditoriaUnitaria());

    $inactiva = $caso->ejecutar($id, 'Inactivo', 9);
    $activa = $caso->ejecutar($id, 'Activo', 9);

    expect($inactiva->estado)->toBe('Inactivo')
        ->and($activa->estado)->toBe('Activo')
        ->and($repo->historial)->toBe([[$id, 'Activo', 'Inactivo', 9], [$id, 'Inactivo', 'Activo', 9]]);
});

it('lanza EstadoSinCambio si ya está en ese estado', function () {
    $repo = new RepositorioEnMemoria;
    $id = competenciaActiva($repo);

    (new CambiarEstadoCompetencia($repo, auditoriaUnitaria()))->ejecutar($id, 'Activo');
})->throws(EstadoSinCambio::class);

it('lanza CompetenciaNoEncontrada si no existe', function () {
    (new CambiarEstadoCompetencia(new RepositorioEnMemoria, auditoriaUnitaria()))->ejecutar(99, 'Inactivo');
})->throws(CompetenciaNoEncontrada::class);

it('no activa una competencia incompleta e indica lo que falta', function () {
    $repo = new RepositorioEnMemoria;
    $base = $repo->competencias[1] = new Competencia(
        id: 1, codigo: 'COMP-0001', nombre: 'Heredada', descripcion: 'd', tipo: TipoCompetencia::Cardinal,
        estado: EstadoCompetencia::Inactivo, version: 2,
    );

    try {
        (new CambiarEstadoCompetencia($repo, auditoriaUnitaria()))->ejecutar($base->id, 'Activo');
        $this->fail('Debió lanzar CompetenciaIncompleta.');
    } catch (CompetenciaIncompleta $e) {
        expect($e->faltantes)->toBe(['grados', 'comportamientos', 'preguntas'])
            ->and($e->getMessage())->toBe('No se puede activar: faltan los grados, los comportamientos y las preguntas.');
    }

    expect($repo->historial)->toBe([]);
});

it('aplica otra escala cambiando solo las etiquetas y registra el historial de escala', function () {
    $repo = new RepositorioEnMemoria;
    $id = competenciaActiva($repo);

    $dto = (new AplicarEscala($repo, auditoriaUnitaria()))->ejecutar($id, 2, 4);

    expect($dto->escala['nombre'])->toBe('Números')
        ->and(array_column($dto->gradosDetalle, 'etiqueta'))->toBe(['Nivel 1', 'Nivel 2', 'Nivel 3', 'Nivel 4'])
        ->and(array_column($dto->gradosDetalle, 'descripcion'))->toBe(['a', 'b', 'c', 'd'])
        ->and($dto->version)->toBe(2)
        ->and($repo->historialDeEscala)->toBe([[$id, 'Letras', 'Números', 4]]);
});

it('rechaza aplicar la misma escala o una que no existe', function () {
    $repo = new RepositorioEnMemoria;
    $id = competenciaActiva($repo);
    $caso = new AplicarEscala($repo, auditoriaUnitaria());

    expect(fn () => $caso->ejecutar($id, 1))->toThrow(EscalaSinCambio::class)
        ->and(fn () => $caso->ejecutar($id, 99))->toThrow(EscalaNoEncontrada::class)
        ->and(fn () => $caso->ejecutar(99, 2))->toThrow(CompetenciaNoEncontrada::class);
});
