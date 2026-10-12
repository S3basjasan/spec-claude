<?php

use App\Modules\Estructura\Application\DTO\FiltroComportamientosDTO;
use App\Modules\Estructura\Application\UseCases\ActualizarComportamientos;
use App\Modules\Estructura\Application\UseCases\CrearComportamientos;
use App\Modules\Estructura\Application\UseCases\ListarCompetenciasDisponibles;
use App\Modules\Estructura\Application\UseCases\ListarComportamientos;
use App\Modules\Estructura\Application\UseCases\ObtenerComportamientos;
use App\Modules\Estructura\Domain\Exceptions\CompetenciaNoDisponible;
use App\Modules\Estructura\Domain\Exceptions\CompetenciaYaTieneRegistro;
use App\Modules\Estructura\Domain\Exceptions\RegistroModificado;
use App\Modules\Estructura\Domain\Exceptions\RegistroNoEncontrado;
use App\Modules\Estructura\Domain\ValueObjects\EstadoCompetencia;
use App\Modules\Estructura\Domain\ValueObjects\TipoCompetencia;
use Tests\Unit\Estructura\Comportamientos\RepositorioComportamientosEnMemoria;

function textosUnit(string $texto = 'Texto'): array
{
    return ['A' => [$texto], 'B' => [$texto], 'C' => [$texto], 'D' => [$texto]];
}

it('crea el registro recortando los textos y devuelve su DTO con versión 1', function () {
    $repo = new RepositorioComportamientosEnMemoria;
    $competencia = $repo->agregarCompetencia('Liderazgo');

    $dto = (new CrearComportamientos($repo))->ejecutar($competencia->id, textosUnit('  Dirige  '), 4);

    expect($dto->version)->toBe(1)
        ->and($dto->nombre)->toBe('Liderazgo')
        ->and($dto->comportamientos['A'])->toBe(['Dirige'])
        ->and($repo->ultimoActor)->toBe(4);
});

it('propaga competencia inactiva, inexistente o con registro', function () {
    $repo = new RepositorioComportamientosEnMemoria;
    $inactiva = $repo->agregarCompetencia('Retirada', EstadoCompetencia::Inactivo);
    $activa = $repo->agregarCompetencia('Activa');
    $caso = new CrearComportamientos($repo);
    $caso->ejecutar($activa->id, textosUnit());

    expect(fn () => $caso->ejecutar($inactiva->id, textosUnit()))->toThrow(CompetenciaNoDisponible::class)
        ->and(fn () => $caso->ejecutar(99, textosUnit()))->toThrow(CompetenciaNoDisponible::class)
        ->and(fn () => $caso->ejecutar($activa->id, textosUnit()))->toThrow(CompetenciaYaTieneRegistro::class);
});

it('lista competencias disponibles: activas y sin registro', function () {
    $repo = new RepositorioComportamientosEnMemoria;
    $repo->agregarCompetencia('Retirada', EstadoCompetencia::Inactivo);
    $con = $repo->agregarCompetencia('Con registro');
    $repo->agregarCompetencia('Libre');
    (new CrearComportamientos($repo))->ejecutar($con->id, textosUnit());

    $disponibles = (new ListarCompetenciasDisponibles($repo))->ejecutar();

    expect($disponibles)->toHaveCount(1)->and($disponibles[0]->nombre)->toBe('Libre');
});

it('actualiza con la versión correcta y sube la versión', function () {
    $repo = new RepositorioComportamientosEnMemoria;
    $c = $repo->agregarCompetencia('Liderazgo');
    (new CrearComportamientos($repo))->ejecutar($c->id, textosUnit('Uno'), 1);

    $dto = (new ActualizarComportamientos($repo))->ejecutar($c->id, 1, textosUnit(' Dos '), 2);

    expect($dto->version)->toBe(2)->and($dto->comportamientos['B'])->toBe(['Dos'])->and($repo->ultimoActor)->toBe(2);
});

it('guardar sin cambios no escribe ni cambia la versión', function () {
    $repo = new RepositorioComportamientosEnMemoria;
    $c = $repo->agregarCompetencia('Liderazgo');
    (new CrearComportamientos($repo))->ejecutar($c->id, textosUnit('Uno'), 1);
    $antes = $repo->escrituras;

    $dto = (new ActualizarComportamientos($repo))->ejecutar($c->id, 1, textosUnit(' Uno '), 2);

    expect($dto->version)->toBe(1)->and($repo->escrituras)->toBe($antes);
});

it('propaga versión desactualizada y registro inexistente', function () {
    $repo = new RepositorioComportamientosEnMemoria;
    $c = $repo->agregarCompetencia('Liderazgo');
    (new CrearComportamientos($repo))->ejecutar($c->id, textosUnit('Uno'));
    $caso = new ActualizarComportamientos($repo);

    expect(fn () => $caso->ejecutar($c->id, 5, textosUnit('Dos')))->toThrow(RegistroModificado::class)
        ->and(fn () => $caso->ejecutar(99, 1, textosUnit('Dos')))->toThrow(RegistroNoEncontrado::class);
});

it('obtiene un registro y lanza RegistroNoEncontrado si no existe', function () {
    $repo = new RepositorioComportamientosEnMemoria;
    $c = $repo->agregarCompetencia('Liderazgo');
    (new CrearComportamientos($repo))->ejecutar($c->id, textosUnit());
    $caso = new ObtenerComportamientos($repo);

    expect($caso->ejecutar($c->id)->codigo)->toBe('COMP-0001')
        ->and(fn () => $caso->ejecutar(99))->toThrow(RegistroNoEncontrado::class);
});

it('lista aplicando filtros de texto, tipo y estado y paginando', function () {
    $repo = new RepositorioComportamientosEnMemoria;
    $crear = new CrearComportamientos($repo);
    foreach ([['Alfa', EstadoCompetencia::Activo, TipoCompetencia::Cardinal], ['Beta', EstadoCompetencia::Activo, TipoCompetencia::Especifica], ['Gama', EstadoCompetencia::Activo, TipoCompetencia::Especifica]] as [$n, $e, $t]) {
        $crear->ejecutar($repo->agregarCompetencia($n, $e, $t)->id, textosUnit());
    }
    $listar = new ListarComportamientos($repo);

    expect($listar->ejecutar(new FiltroComportamientosDTO(q: ' alf '))->total)->toBe(1)
        ->and($listar->ejecutar(new FiltroComportamientosDTO(q: 'xyz'))->total)->toBe(0)
        ->and($listar->ejecutar(new FiltroComportamientosDTO(tipo: 'Especifica'))->total)->toBe(2)
        ->and($listar->ejecutar(new FiltroComportamientosDTO(estado: 'Inactivo'))->total)->toBe(0)
        ->and($listar->ejecutar(new FiltroComportamientosDTO(porPagina: 2, pagina: 2))->items)->toHaveCount(1);
});

it('el módulo no define casos de uso de estado, borrado ni historial propios', function () {
    $archivos = array_map('basename', glob(app_path('Modules/Estructura/Application/UseCases/*.php')));

    foreach ($archivos as $archivo) {
        if (str_contains($archivo, 'Comportamientos')) {
            expect($archivo)->not->toMatch('/Estado|Eliminar|Borrar|Historial|Desactivar|Activar/');
        }
    }
});
