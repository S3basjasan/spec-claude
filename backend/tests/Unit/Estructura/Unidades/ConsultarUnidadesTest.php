<?php

use App\Modules\Estructura\Application\DTO\FiltroUnidadesDTO;
use App\Modules\Estructura\Application\UseCases\ListarUnidades;
use App\Modules\Estructura\Application\UseCases\ObtenerUnidad;
use App\Modules\Estructura\Domain\Exceptions\UnidadNoEncontrada;
use App\Modules\Estructura\Domain\Services\NormalizadorDeTexto;
use App\Modules\Estructura\Domain\ValueObjects\EstadoUnidad;
use Tests\Unit\Estructura\Unidades\NombresDeUsuariosEnMemoria;
use Tests\Unit\Estructura\Unidades\RepositorioUnidadesEnMemoria;

function repositorioConArbol(): RepositorioUnidadesEnMemoria
{
    $repo = new RepositorioUnidadesEnMemoria;
    $raiz = $repo->agregar('Gerencia general', null, 1);
    $talento = $repo->agregar('Talento humano', $raiz, 2);
    $tecnologia = $repo->agregar('Tecnología', $raiz, 2);
    $repo->agregar('Desarrollo', $tecnologia, 3);
    $repo->agregar('Reclutamiento', $talento, 4);
    $repo->agregar('Operaciones', $raiz, 2, EstadoUnidad::Inactiva);

    return $repo;
}

function listarUnidadesCon(RepositorioUnidadesEnMemoria $repo): ListarUnidades
{
    return new ListarUnidades($repo, new NormalizadorDeTexto);
}

it('lista el árbol completo en orden jerárquico cuando no hay filtros', function () {
    $listado = listarUnidadesCon(repositorioConArbol())->ejecutar(new FiltroUnidadesDTO);

    expect($listado->total)->toBe(6)
        ->and(array_map(fn ($u) => $u->nombre, $listado->items))->toBe(['Gerencia general', 'Talento humano', 'Reclutamiento', 'Tecnología', 'Desarrollo', 'Operaciones'])
        ->and(array_map(fn ($u) => $u->nivel, $listado->items))->toBe([0, 1, 2, 1, 2, 1]);
});

it('filtra por estado sin cambiar el nivel de las filas que quedan', function () {
    $repo = repositorioConArbol();

    $activas = listarUnidadesCon($repo)->ejecutar(new FiltroUnidadesDTO(estado: 'Activa'));
    $inactivas = listarUnidadesCon($repo)->ejecutar(new FiltroUnidadesDTO(estado: 'Inactiva'));

    expect($activas->total)->toBe(5)
        ->and(array_map(fn ($u) => $u->nombre, $inactivas->items))->toBe(['Operaciones'])
        ->and($inactivas->items[0]->nivel)->toBe(1)
        ->and($inactivas->items[0]->unidadPadre['nombre'])->toBe('Gerencia general');
});

it('busca por código, nombre y tipo sin distinguir mayúsculas ni tildes, sin añadir las unidades superiores', function () {
    $repo = repositorioConArbol();
    $listar = listarUnidadesCon($repo);

    expect(array_map(fn ($u) => $u->nombre, $listar->ejecutar(new FiltroUnidadesDTO(q: ' TECNOLOGIA '))->items))->toBe(['Tecnología'])
        ->and(array_map(fn ($u) => $u->nombre, $listar->ejecutar(new FiltroUnidadesDTO(q: 'seccion'))->items))->toBe(['Reclutamiento'])
        ->and(array_map(fn ($u) => $u->nombre, $listar->ejecutar(new FiltroUnidadesDTO(q: 'uo-002'))->items))->toBe(['Talento humano'])
        ->and($listar->ejecutar(new FiltroUnidadesDTO(q: 'reclutamiento'))->items[0]->nivel)->toBe(2)
        ->and($listar->ejecutar(new FiltroUnidadesDTO(q: 'zzz'))->total)->toBe(0);
});

it('obtiene el detalle de una unidad inactiva y falla si no existe', function () {
    $repo = repositorioConArbol();
    $caso = new ObtenerUnidad($repo, new NombresDeUsuariosEnMemoria);

    expect($caso->ejecutar(6)->estado)->toBe('Inactiva')
        ->and($caso->ejecutar(6)->fechaSupresion)->toBe('2026-10-14')
        ->and(fn () => $caso->ejecutar(99))->toThrow(UnidadNoEncontrada::class);
});

it('incluye la auditoría con nombres solo para el administrador', function () {
    $repo = repositorioConArbol();
    $repo->filas[2]['creadoPor'] = 1;
    $repo->filas[2]['modificadoPor'] = 2;
    $repo->filas[2]['version'] = 2;
    $caso = new ObtenerUnidad($repo, new NombresDeUsuariosEnMemoria);

    $conAuditoria = $caso->ejecutar(2, true)->auditoria;
    $sinAuditoria = $caso->ejecutar(2, false)->auditoria;

    expect($conAuditoria['created_by'])->toBe(['id' => 1, 'nombre' => 'Ana'])
        ->and($conAuditoria['updated_by'])->toBe(['id' => 2, 'nombre' => 'Luis'])
        ->and($sinAuditoria)->toBeNull();
});
