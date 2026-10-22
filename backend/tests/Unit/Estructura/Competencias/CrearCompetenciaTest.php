<?php

use App\Modules\Estructura\Application\UseCases\CrearCompetencia;
use App\Modules\Estructura\Domain\Exceptions\CategoriasInvalidas;
use App\Modules\Estructura\Domain\Exceptions\EscalaNoEncontrada;
use App\Modules\Estructura\Domain\Exceptions\NombreDuplicado;
use Tests\Unit\Estructura\Competencias\RepositorioEnMemoria;

require_once __DIR__.'/Ayudas.php';

function crearCaso(RepositorioEnMemoria $repo): CrearCompetencia
{
    return new CrearCompetencia($repo, validadorUnitario(), auditoriaUnitaria());
}

it('crea la competencia completa y activa recortando textos y devuelve su DTO con auditoría', function () {
    $repo = new RepositorioEnMemoria;

    $dto = crearCaso($repo)->ejecutar(datosUnitarios('  Liderazgo '), 3);

    expect($dto->nombre)->toBe('Liderazgo')
        ->and($dto->descripcion)->toBe('Guiar a otros.')
        ->and($dto->tipo)->toBe('Especifica')
        ->and($dto->estado)->toBe('Activo')
        ->and($dto->codigo)->toBe('COMP-0001')
        ->and($dto->version)->toBe(1)
        ->and($dto->completa)->toBeTrue()
        ->and($dto->escala)->toBe(['tipo_grado_id' => 1, 'nombre' => 'Letras'])
        ->and(array_column($dto->gradosDetalle, 'etiqueta'))->toBe(['Grado A', 'Grado B', 'Grado C', 'Grado D'])
        ->and($dto->preguntas)->toHaveCount(4)
        ->and($dto->auditoria['created_by'])->toBe('Usuario 3')
        ->and($repo->ultimoActor)->toBe(3);
});

it('propaga NombreDuplicado sin distinguir mayúsculas ni tildes', function () {
    $repo = new RepositorioEnMemoria;
    $caso = crearCaso($repo);
    $caso->ejecutar(datosUnitarios('Liderazgo'));

    $caso->ejecutar(datosUnitarios('LIDÉRAZGO'));
})->throws(NombreDuplicado::class);

it('rechaza una escala que no existe', function () {
    crearCaso(new RepositorioEnMemoria)->ejecutar(datosUnitarios('Liderazgo', 99));
})->throws(EscalaNoEncontrada::class);

it('exige los nombres propios de las categorías con la escala Categorías', function () {
    crearCaso(new RepositorioEnMemoria)->ejecutar(datosUnitarios('Liderazgo', 3));
})->throws(CategoriasInvalidas::class);

it('crea con la escala Categorías y nombres propios recortados', function () {
    $dto = crearCaso(new RepositorioEnMemoria)->ejecutar(datosUnitarios('Equipo', 3, [' Maestría ', 'Avanzado', 'Competente', 'Inicial']));

    expect($dto->categorias)->toBe(['Maestría', 'Avanzado', 'Competente', 'Inicial'])
        ->and(array_column($dto->gradosDetalle, 'etiqueta'))->toBe(['Maestría', 'Avanzado', 'Competente', 'Inicial']);
});

it('rechaza nombres de categoría repetidos, largos o vacíos', function (array $nombres) {
    crearCaso(new RepositorioEnMemoria)->ejecutar(datosUnitarios('Equipo', 3, $nombres));
})->with([
    'repetidos' => [['Buena', ' búena ', 'Alta', 'Baja']],
    'largo' => [[str_repeat('x', 21), 'B', 'C', 'D']],
    'vacío' => [['A', 'B', 'C', '  ']],
])->throws(CategoriasInvalidas::class);
