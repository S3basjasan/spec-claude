<?php

use App\Modules\Estructura\Application\UseCases\CambiarEstadoCompetencia;
use App\Modules\Estructura\Domain\Exceptions\CompetenciaNoEncontrada;
use App\Modules\Estructura\Domain\Exceptions\EstadoSinCambio;
use App\Modules\Estructura\Domain\ValueObjects\TipoCompetencia;
use Tests\Unit\Estructura\Competencias\RepositorioEnMemoria;

function repoConUnaActiva(): array
{
    $repo = new RepositorioEnMemoria;
    $competencia = $repo->crear('Liderazgo', 'Guiar.', TipoCompetencia::Cardinal, ['A' => 'a', 'B' => 'b', 'C' => 'c', 'D' => 'd'], null);

    return [$repo, $competencia->id];
}

it('desactiva y vuelve a activar registrando cada cambio', function () {
    [$repo, $id] = repoConUnaActiva();
    $caso = new CambiarEstadoCompetencia($repo);

    $inactiva = $caso->ejecutar($id, 'Inactivo', 4);
    $activa = $caso->ejecutar($id, 'Activo', 5);

    expect($inactiva->estado)->toBe('Inactivo')
        ->and($activa->estado)->toBe('Activo')
        ->and($activa->version)->toBe(1)
        ->and($repo->historial)->toBe([[$id, 'Activo', 'Inactivo', 4], [$id, 'Inactivo', 'Activo', 5]]);
});

it('propaga EstadoSinCambio sin registrar historial', function () {
    [$repo, $id] = repoConUnaActiva();

    try {
        (new CambiarEstadoCompetencia($repo))->ejecutar($id, 'Activo');
        $this->fail('Debió lanzar EstadoSinCambio');
    } catch (EstadoSinCambio) {
        expect($repo->historial)->toBe([]);
    }
});

it('propaga CompetenciaNoEncontrada', function () {
    (new CambiarEstadoCompetencia(new RepositorioEnMemoria))->ejecutar(9, 'Inactivo');
})->throws(CompetenciaNoEncontrada::class);
