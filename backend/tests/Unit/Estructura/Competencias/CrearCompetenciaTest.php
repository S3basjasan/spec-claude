<?php

use App\Modules\Estructura\Application\DTO\DatosCompetenciaDTO;
use App\Modules\Estructura\Application\UseCases\CrearCompetencia;
use App\Modules\Estructura\Domain\Exceptions\NombreDuplicado;
use Tests\Unit\Estructura\Competencias\RepositorioEnMemoria;

function datosDto(string $nombre = 'Liderazgo'): DatosCompetenciaDTO
{
    return new DatosCompetenciaDTO($nombre, ' Guiar a otros. ', 'Especifica', ['A' => 'a', 'B' => 'b', 'C' => 'c', 'D' => 'd']);
}

it('crea la competencia activa recortando textos y devuelve su DTO', function () {
    $repo = new RepositorioEnMemoria;

    $dto = (new CrearCompetencia($repo))->ejecutar(datosDto('  Liderazgo '), 3);

    expect($dto->nombre)->toBe('Liderazgo')
        ->and($dto->descripcion)->toBe('Guiar a otros.')
        ->and($dto->tipo)->toBe('Especifica')
        ->and($dto->estado)->toBe('Activo')
        ->and($dto->codigo)->toBe('COMP-0001')
        ->and($dto->version)->toBe(1)
        ->and($dto->grados)->toHaveCount(4)
        ->and($repo->ultimoActor)->toBe(3);
});

it('propaga NombreDuplicado', function () {
    $repo = new RepositorioEnMemoria;
    $caso = new CrearCompetencia($repo);
    $caso->ejecutar(datosDto('Liderazgo'));

    $caso->ejecutar(datosDto('LIDERAZGO'));
})->throws(NombreDuplicado::class);
