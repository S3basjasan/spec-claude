<?php

use App\Modules\Estructura\Application\DTO\FiltroCompetenciasDTO;
use App\Modules\Estructura\Application\UseCases\ListarCompetencias;
use App\Modules\Estructura\Application\UseCases\ObtenerCompetencia;
use App\Modules\Estructura\Domain\Exceptions\CompetenciaNoEncontrada;
use App\Modules\Estructura\Domain\ValueObjects\EstadoCompetencia;
use App\Modules\Estructura\Domain\ValueObjects\TipoCompetencia;
use Tests\Unit\Estructura\Competencias\RepositorioEnMemoria;

function grados4(): array
{
    return ['A' => 'a', 'B' => 'b', 'C' => 'c', 'D' => 'd'];
}

it('lista con el filtro recortado, el tipo y el estado convertidos y la paginación pedida', function () {
    $repo = new RepositorioEnMemoria;
    foreach (['Uno', 'Dos', 'Tres'] as $nombre) {
        $repo->crear($nombre, 'Descripción.', TipoCompetencia::Cardinal, grados4(), null);
    }

    $listado = (new ListarCompetencias($repo))->ejecutar(new FiltroCompetenciasDTO('  uno ', 'Cardinal', 'Inactivo', 2, 2));

    expect($repo->ultimoQ)->toBe('uno')
        ->and($repo->ultimoTipo)->toBe(TipoCompetencia::Cardinal)
        ->and($repo->ultimoEstado)->toBe(EstadoCompetencia::Inactivo)
        ->and($listado->total)->toBe(3)
        ->and($listado->items)->toHaveCount(1)
        ->and($listado->pagina)->toBe(2)
        ->and($listado->items[0]->estado)->toBe('Activo');
});

it('un estado nulo significa todas las competencias', function () {
    $repo = new RepositorioEnMemoria;

    (new ListarCompetencias($repo))->ejecutar(new FiltroCompetenciasDTO);

    expect($repo->ultimoEstado)->toBeNull();
});

it('ignora un tipo desconocido', function () {
    $repo = new RepositorioEnMemoria;

    (new ListarCompetencias($repo))->ejecutar(new FiltroCompetenciasDTO(null, 'otro'));

    expect($repo->ultimoTipo)->toBeNull();
});

it('obtiene una competencia con su estado y sus grados', function () {
    $repo = new RepositorioEnMemoria;
    $creada = $repo->crear('Liderazgo', 'Guiar.', TipoCompetencia::Cardinal, grados4(), null);

    $dto = (new ObtenerCompetencia($repo))->ejecutar($creada->id);

    expect($dto->codigo)->toBe('COMP-0001')->and($dto->tipo)->toBe('Cardinal')->and($dto->estado)->toBe('Activo')->and($dto->grados)->toBe(grados4());
});

it('obtiene también una competencia inactiva', function () {
    $repo = new RepositorioEnMemoria;
    $creada = $repo->crear('Retirada', 'Guiar.', TipoCompetencia::Cardinal, grados4(), null);
    $repo->cambiarEstado($creada->id, EstadoCompetencia::Inactivo, null);

    expect((new ObtenerCompetencia($repo))->ejecutar($creada->id)->estado)->toBe('Inactivo');
});

it('lanza CompetenciaNoEncontrada si no existe', function () {
    (new ObtenerCompetencia(new RepositorioEnMemoria))->ejecutar(5);
})->throws(CompetenciaNoEncontrada::class);
