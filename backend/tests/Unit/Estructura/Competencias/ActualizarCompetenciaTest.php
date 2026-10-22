<?php

use App\Modules\Estructura\Application\UseCases\ActualizarCompetencia;
use App\Modules\Estructura\Application\UseCases\ActualizarComportamientos;
use App\Modules\Estructura\Application\UseCases\ActualizarPreguntas;
use App\Modules\Estructura\Application\UseCases\CrearCompetencia;
use App\Modules\Estructura\Domain\Exceptions\CompetenciaNoEncontrada;
use App\Modules\Estructura\Domain\Exceptions\ConflictoDeVersion;
use Tests\Unit\Estructura\Competencias\RepositorioEnMemoria;

require_once __DIR__.'/Ayudas.php';

function competenciaCreada(RepositorioEnMemoria $repo, string $nombre = 'Liderazgo'): int
{
    return (new CrearCompetencia($repo, validadorUnitario(), auditoriaUnitaria()))->ejecutar(datosUnitarios($nombre))->id;
}

it('actualiza datos y grados con la versión esperada y sube la versión', function () {
    $repo = new RepositorioEnMemoria;
    $id = competenciaCreada($repo);

    $dto = (new ActualizarCompetencia($repo, validadorUnitario(), auditoriaUnitaria()))
        ->ejecutar($id, 1, datosUnitarios(' Nuevo nombre ', 2), 5);

    expect($dto->nombre)->toBe('Nuevo nombre')
        ->and($dto->version)->toBe(2)
        ->and($dto->escala['nombre'])->toBe('Números')
        ->and(array_column($dto->gradosDetalle, 'etiqueta'))->toBe(['Nivel 1', 'Nivel 2', 'Nivel 3', 'Nivel 4'])
        ->and($repo->ultimoActor)->toBe(5);
});

it('lanza ConflictoDeVersion si la versión no coincide', function () {
    $repo = new RepositorioEnMemoria;
    $id = competenciaCreada($repo);

    (new ActualizarCompetencia($repo, validadorUnitario(), auditoriaUnitaria()))->ejecutar($id, 7, datosUnitarios());
})->throws(ConflictoDeVersion::class);

it('lanza CompetenciaNoEncontrada si no existe', function () {
    (new ActualizarCompetencia(new RepositorioEnMemoria, validadorUnitario(), auditoriaUnitaria()))->ejecutar(99, 1, datosUnitarios());
})->throws(CompetenciaNoEncontrada::class);

it('actualiza los comportamientos y las preguntas subiendo la versión en cada escritura', function () {
    $repo = new RepositorioEnMemoria;
    $id = competenciaCreada($repo);

    $conComportamientos = (new ActualizarComportamientos($repo, auditoriaUnitaria()))
        ->ejecutar($id, 1, [[' x1 ', 'x2', 'x3', 'x4'], ['y1', 'y2', 'y3', 'y4'], ['z1', 'z2', 'z3', 'z4'], ['w1', 'w2', 'w3', 'w4']], 4);
    $conPreguntas = (new ActualizarPreguntas($repo, auditoriaUnitaria()))->ejecutar($id, 2, [' q1 ', 'q2', 'q3', 'q4'], 4);

    expect($conComportamientos->version)->toBe(2)
        ->and($conComportamientos->gradosDetalle[0]['comportamientos'])->toBe(['x1', 'x2', 'x3', 'x4'])
        ->and($conPreguntas->version)->toBe(3)
        ->and($conPreguntas->preguntas)->toBe(['q1', 'q2', 'q3', 'q4']);
});

it('rechaza con ConflictoDeVersion una edición de preguntas sobre una versión vieja', function () {
    $repo = new RepositorioEnMemoria;
    $id = competenciaCreada($repo);
    $caso = new ActualizarPreguntas($repo, auditoriaUnitaria());
    $caso->ejecutar($id, 1, ['a', 'b', 'c', 'd']);

    $caso->ejecutar($id, 1, ['x', 'y', 'z', 'w']);
})->throws(ConflictoDeVersion::class);
