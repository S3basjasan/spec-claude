<?php

use App\Modules\Estructura\Application\DTO\FiltroPreguntasDTO;
use App\Modules\Estructura\Application\UseCases\ActualizarPreguntas;
use App\Modules\Estructura\Application\UseCases\CrearPreguntas;
use App\Modules\Estructura\Application\UseCases\ListarCompetenciasDisponiblesPreguntas;
use App\Modules\Estructura\Application\UseCases\ListarPreguntas;
use App\Modules\Estructura\Application\UseCases\ObtenerPreguntas;
use App\Modules\Estructura\Domain\Exceptions\CompetenciaNoDisponible;
use App\Modules\Estructura\Domain\Exceptions\CompetenciaYaTienePreguntas;
use App\Modules\Estructura\Domain\Exceptions\PreguntasNoEncontradas;
use App\Modules\Estructura\Domain\Exceptions\RegistroModificado;
use App\Modules\Estructura\Domain\ValueObjects\EstadoCompetencia;
use App\Modules\Estructura\Domain\ValueObjects\TipoCompetencia;
use Tests\Unit\Estructura\Preguntas\RepositorioPreguntasEnMemoria;

/** @return array<int, string> */
function textosPreguntasUnit(string $texto = 'Texto'): array
{
    return ["$texto 1", "$texto 2", "$texto 3", "$texto 4"];
}

it('crea el registro recortando los textos y devuelve su DTO con versión 1', function () {
    $repo = new RepositorioPreguntasEnMemoria;
    $competencia = $repo->agregarCompetencia('Liderazgo');

    $dto = (new CrearPreguntas($repo))->ejecutar($competencia->id, ['  Uno  ', 'Dos', 'Tres', 'Cuatro'], 4);

    expect($dto->version)->toBe(1)
        ->and($dto->nombre)->toBe('Liderazgo')
        ->and($dto->preguntas)->toBe(['Uno', 'Dos', 'Tres', 'Cuatro'])
        ->and($repo->ultimoActor)->toBe(4);
});

it('propaga competencia inactiva, inexistente o con registro', function () {
    $repo = new RepositorioPreguntasEnMemoria;
    $inactiva = $repo->agregarCompetencia('Retirada', EstadoCompetencia::Inactivo);
    $activa = $repo->agregarCompetencia('Activa');
    $caso = new CrearPreguntas($repo);
    $caso->ejecutar($activa->id, textosPreguntasUnit());

    expect(fn () => $caso->ejecutar($inactiva->id, textosPreguntasUnit()))->toThrow(CompetenciaNoDisponible::class)
        ->and(fn () => $caso->ejecutar(99, textosPreguntasUnit()))->toThrow(CompetenciaNoDisponible::class)
        ->and(fn () => $caso->ejecutar($activa->id, textosPreguntasUnit()))->toThrow(CompetenciaYaTienePreguntas::class);
});

it('lista competencias disponibles: activas y sin registro', function () {
    $repo = new RepositorioPreguntasEnMemoria;
    $repo->agregarCompetencia('Retirada', EstadoCompetencia::Inactivo);
    $con = $repo->agregarCompetencia('Con registro');
    $repo->agregarCompetencia('Libre');
    (new CrearPreguntas($repo))->ejecutar($con->id, textosPreguntasUnit());

    $disponibles = (new ListarCompetenciasDisponiblesPreguntas($repo))->ejecutar();

    expect($disponibles)->toHaveCount(1)->and($disponibles[0]->nombre)->toBe('Libre');
});

it('actualiza con la versión correcta y sube la versión', function () {
    $repo = new RepositorioPreguntasEnMemoria;
    $c = $repo->agregarCompetencia('Liderazgo');
    (new CrearPreguntas($repo))->ejecutar($c->id, textosPreguntasUnit('Uno'), 1);

    $dto = (new ActualizarPreguntas($repo))->ejecutar($c->id, 1, [' Dos 1 ', 'Dos 2', 'Dos 3', 'Dos 4'], 2);

    expect($dto->version)->toBe(2)->and($dto->preguntas[0])->toBe('Dos 1')->and($repo->ultimoActor)->toBe(2);
});

it('guardar sin cambios no escribe ni cambia la versión', function () {
    $repo = new RepositorioPreguntasEnMemoria;
    $c = $repo->agregarCompetencia('Liderazgo');
    (new CrearPreguntas($repo))->ejecutar($c->id, textosPreguntasUnit('Uno'), 1);
    $antes = $repo->escrituras;

    $dto = (new ActualizarPreguntas($repo))->ejecutar($c->id, 1, array_map(fn ($t) => " $t ", textosPreguntasUnit('Uno')), 2);

    expect($dto->version)->toBe(1)->and($repo->escrituras)->toBe($antes);
});

it('propaga versión desactualizada y registro inexistente', function () {
    $repo = new RepositorioPreguntasEnMemoria;
    $c = $repo->agregarCompetencia('Liderazgo');
    (new CrearPreguntas($repo))->ejecutar($c->id, textosPreguntasUnit('Uno'));
    $caso = new ActualizarPreguntas($repo);

    expect(fn () => $caso->ejecutar($c->id, 5, textosPreguntasUnit('Dos')))->toThrow(RegistroModificado::class)
        ->and(fn () => $caso->ejecutar(99, 1, textosPreguntasUnit('Dos')))->toThrow(PreguntasNoEncontradas::class);
});

it('obtiene un registro y lanza PreguntasNoEncontradas si no existe', function () {
    $repo = new RepositorioPreguntasEnMemoria;
    $c = $repo->agregarCompetencia('Liderazgo');
    (new CrearPreguntas($repo))->ejecutar($c->id, textosPreguntasUnit());
    $caso = new ObtenerPreguntas($repo);

    expect($caso->ejecutar($c->id)->codigo)->toBe('COMP-0001')
        ->and(fn () => $caso->ejecutar(99))->toThrow(PreguntasNoEncontradas::class);
});

it('lista aplicando filtros de texto, tipo y estado y paginando', function () {
    $repo = new RepositorioPreguntasEnMemoria;
    $crear = new CrearPreguntas($repo);
    foreach ([['Alfa', TipoCompetencia::Cardinal], ['Beta', TipoCompetencia::Especifica], ['Gama', TipoCompetencia::Especifica]] as [$n, $t]) {
        $crear->ejecutar($repo->agregarCompetencia($n, EstadoCompetencia::Activo, $t)->id, textosPreguntasUnit());
    }
    $listar = new ListarPreguntas($repo);

    expect($listar->ejecutar(new FiltroPreguntasDTO(q: ' alf '))->total)->toBe(1)
        ->and($listar->ejecutar(new FiltroPreguntasDTO(q: 'xyz'))->total)->toBe(0)
        ->and($listar->ejecutar(new FiltroPreguntasDTO(tipo: 'Especifica'))->total)->toBe(2)
        ->and($listar->ejecutar(new FiltroPreguntasDTO(estado: 'Inactivo'))->total)->toBe(0)
        ->and($listar->ejecutar(new FiltroPreguntasDTO(porPagina: 2, pagina: 2))->items)->toHaveCount(1);
});

it('el módulo no define casos de uso de estado, borrado ni historial propios para preguntas', function () {
    $archivos = array_map('basename', glob(app_path('Modules/Estructura/Application/UseCases/*.php')));

    foreach ($archivos as $archivo) {
        if (str_contains($archivo, 'Pregunta')) {
            expect($archivo)->not->toMatch('/Estado|Eliminar|Borrar|Historial|Desactivar|Activar/');
        }
    }
});
