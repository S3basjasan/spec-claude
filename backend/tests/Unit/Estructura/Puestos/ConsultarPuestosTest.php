<?php

use App\Modules\Estructura\Application\DTO\FiltroPuestosDTO;
use App\Modules\Estructura\Application\UseCases\ListarPuestos;
use App\Modules\Estructura\Application\UseCases\ObtenerPuesto;
use App\Modules\Estructura\Domain\Exceptions\PuestoNoEncontrado;
use App\Modules\Estructura\Domain\ValueObjects\EstadoCompetencia;
use App\Modules\Estructura\Domain\ValueObjects\EstadoPuesto;
use Tests\Unit\Estructura\Puestos\RepositorioPuestosEnMemoria;

beforeEach(function () {
    $this->repositorio = new RepositorioPuestosEnMemoria;
    $this->repositorio->agregarCompetencia('Liderazgo');
    $this->repositorio->agregarCompetencia('Adaptabilidad', EstadoCompetencia::Inactivo);
    $this->repositorio->agregarPuesto('Jefe de talento', EstadoPuesto::Activo, 1);
    $this->repositorio->agregarPuesto('Analista', EstadoPuesto::Activo, 2);
    $this->repositorio->agregarPuesto('Asistente', EstadoPuesto::Inactivo);
});

it('lista por nombre y devuelve la página pedida con el total', function () {
    $listado = (new ListarPuestos($this->repositorio))->ejecutar(new FiltroPuestosDTO(null, null, 1, 2));

    expect(array_map(fn ($p) => $p->nombre, $listado->items))->toBe(['Analista', 'Asistente'])
        ->and($listado->total)->toBe(3)
        ->and($listado->pagina)->toBe(1)
        ->and($listado->porPagina)->toBe(2);
});

it('filtra por estado y por texto recortado', function () {
    $listar = new ListarPuestos($this->repositorio);

    $activos = $listar->ejecutar(new FiltroPuestosDTO(null, 'Activo'));
    $inactivos = $listar->ejecutar(new FiltroPuestosDTO(null, 'Inactivo'));
    $texto = $listar->ejecutar(new FiltroPuestosDTO('  analista  ', null));

    expect($activos->total)->toBe(2)
        ->and($inactivos->items[0]->nombre)->toBe('Asistente')
        ->and($texto->total)->toBe(1)
        ->and($texto->items[0]->nombre)->toBe('Analista');
});

it('obtiene un puesto con sus listas, también si está inactivo', function () {
    $puesto = (new ObtenerPuesto($this->repositorio))->ejecutar(3);

    expect($puesto->nombre)->toBe('Asistente')
        ->and($puesto->estado)->toBe('Inactivo')
        ->and($puesto->funciones)->toHaveCount(1)
        ->and($puesto->requisitos)->toHaveCount(2);
});

it('muestra las competencias inactivas con su grado', function () {
    $puesto = (new ObtenerPuesto($this->repositorio))->ejecutar(2);

    expect($puesto->competencias[0]['estado'])->toBe('Inactivo')
        ->and($puesto->competencias[0]['grado_requerido'])->toBe('B');
});

it('informa cuando el puesto no existe', function () {
    expect(fn () => (new ObtenerPuesto($this->repositorio))->ejecutar(99))->toThrow(PuestoNoEncontrado::class);
});
