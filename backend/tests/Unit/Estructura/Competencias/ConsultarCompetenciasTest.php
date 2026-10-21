<?php

use App\Modules\Estructura\Application\DTO\FiltroCompetenciasDTO;
use App\Modules\Estructura\Application\UseCases\CrearCompetencia;
use App\Modules\Estructura\Application\UseCases\ListarCompetencias;
use App\Modules\Estructura\Application\UseCases\ObtenerCompetencia;
use App\Modules\Estructura\Domain\Exceptions\CompetenciaNoEncontrada;
use App\Modules\Estructura\Domain\ValueObjects\EstadoCompetencia;
use App\Modules\Estructura\Domain\ValueObjects\TipoCompetencia;
use Tests\Unit\Estructura\Competencias\RepositorioEnMemoria;

require_once __DIR__.'/Ayudas.php';

it('lista con el total, la página y los filtros normalizados', function () {
    $repo = new RepositorioEnMemoria;
    $crear = new CrearCompetencia($repo, validadorUnitario(), auditoriaUnitaria());
    foreach (['Alfa', 'Beta', 'Gamma'] as $nombre) {
        $crear->ejecutar(datosUnitarios($nombre));
    }

    $listado = (new ListarCompetencias($repo))->ejecutar(new FiltroCompetenciasDTO('  lider ', 'Especifica', 'Activo', 1, 2));

    expect($listado->items)->toHaveCount(2)
        ->and($listado->total)->toBe(3)
        ->and($listado->pagina)->toBe(1)
        ->and($listado->porPagina)->toBe(2)
        ->and($repo->ultimoQ)->toBe('lider')
        ->and($repo->ultimoTipo)->toBe(TipoCompetencia::Especifica)
        ->and($repo->ultimoEstado)->toBe(EstadoCompetencia::Activo);
});

it('sin estado en el filtro lista activas e inactivas', function () {
    $repo = new RepositorioEnMemoria;
    (new ListarCompetencias($repo))->ejecutar(new FiltroCompetenciasDTO);

    expect($repo->ultimoEstado)->toBeNull();
});

it('obtiene el detalle con la auditoría solo para el administrador', function () {
    $repo = new RepositorioEnMemoria;
    $id = (new CrearCompetencia($repo, validadorUnitario(), auditoriaUnitaria()))->ejecutar(datosUnitarios(), 7)->id;
    $obtener = new ObtenerCompetencia($repo, auditoriaUnitaria());

    expect($obtener->ejecutar($id, true)->auditoria['created_by'])->toBe('Usuario 7')
        ->and($obtener->ejecutar($id, false)->auditoria)->toBeNull()
        ->and($obtener->ejecutar($id)->auditoria)->toBeNull();
});

it('lanza CompetenciaNoEncontrada si no existe', function () {
    (new ObtenerCompetencia(new RepositorioEnMemoria, auditoriaUnitaria()))->ejecutar(99);
})->throws(CompetenciaNoEncontrada::class);
