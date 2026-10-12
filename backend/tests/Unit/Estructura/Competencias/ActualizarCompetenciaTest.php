<?php

use App\Modules\Estructura\Application\DTO\DatosCompetenciaDTO;
use App\Modules\Estructura\Application\UseCases\ActualizarCompetencia;
use App\Modules\Estructura\Domain\Exceptions\CompetenciaNoEncontrada;
use App\Modules\Estructura\Domain\Exceptions\ConflictoDeVersion;
use App\Modules\Estructura\Domain\ValueObjects\EstadoCompetencia;
use App\Modules\Estructura\Domain\ValueObjects\TipoCompetencia;
use Tests\Unit\Estructura\Competencias\RepositorioEnMemoria;

function datosNuevos(): DatosCompetenciaDTO
{
    return new DatosCompetenciaDTO(' Nuevo ', ' Nueva descripción ', 'Especifica', ['A' => '1', 'B' => '2', 'C' => '3', 'D' => '4']);
}

it('actualiza con la versión esperada y conserva código y estado', function () {
    $repo = new RepositorioEnMemoria;
    $original = $repo->crear('Viejo', 'Vieja.', TipoCompetencia::Cardinal, ['A' => 'a', 'B' => 'b', 'C' => 'c', 'D' => 'd'], 1);
    $repo->cambiarEstado($original->id, EstadoCompetencia::Inactivo, 1);

    $dto = (new ActualizarCompetencia($repo))->ejecutar($original->id, 1, datosNuevos(), 2);

    expect($dto->nombre)->toBe('Nuevo')
        ->and($dto->descripcion)->toBe('Nueva descripción')
        ->and($dto->tipo)->toBe('Especifica')
        ->and($dto->estado)->toBe('Inactivo')
        ->and($dto->version)->toBe(2)
        ->and($dto->codigo)->toBe($original->codigo)
        ->and($dto->grados['D'])->toBe('4')
        ->and($repo->ultimoActor)->toBe(2);
});

it('lanza ConflictoDeVersion con una versión desactualizada', function () {
    $repo = new RepositorioEnMemoria;
    $original = $repo->crear('Viejo', 'Vieja.', TipoCompetencia::Cardinal, ['A' => 'a', 'B' => 'b', 'C' => 'c', 'D' => 'd'], null);

    (new ActualizarCompetencia($repo))->ejecutar($original->id, 7, datosNuevos());
})->throws(ConflictoDeVersion::class);

it('lanza CompetenciaNoEncontrada si no existe', function () {
    (new ActualizarCompetencia(new RepositorioEnMemoria))->ejecutar(9, 1, datosNuevos());
})->throws(CompetenciaNoEncontrada::class);
