<?php

use App\Modules\Estructura\Application\UseCases\ActualizarPuesto;
use App\Modules\Estructura\Domain\Exceptions\CodigoDePuestoNoEditable;
use App\Modules\Estructura\Domain\Exceptions\CompetenciasDePuestoInvalidas;
use App\Modules\Estructura\Domain\Exceptions\NombreDePuestoDuplicado;
use App\Modules\Estructura\Domain\Exceptions\PuestoModificado;
use App\Modules\Estructura\Domain\Exceptions\PuestoNoEncontrado;
use App\Modules\Estructura\Domain\Services\ReglasDeCompetenciasDePuesto;
use App\Modules\Estructura\Domain\ValueObjects\AsignacionDeCompetencia;
use App\Modules\Estructura\Domain\ValueObjects\DatosPuesto;
use App\Modules\Estructura\Domain\ValueObjects\EstadoCompetencia;
use App\Modules\Estructura\Domain\ValueObjects\EstadoPuesto;
use Tests\Unit\Estructura\Puestos\RepositorioPuestosEnMemoria;

// `datosDePuesto()` se define en CrearPuestoTest.php (mismo directorio).

beforeEach(function () {
    $this->repositorio = new RepositorioPuestosEnMemoria;
    $this->repositorio->agregarCompetencia('Liderazgo');
    $this->repositorio->agregarCompetencia('Adaptabilidad', EstadoCompetencia::Inactivo);
    $this->repositorio->agregarPuesto('Analista', EstadoPuesto::Activo, 1);
    $this->repositorio->agregarPuesto('Retirado', EstadoPuesto::Inactivo, 2);
    $this->actualizar = new ActualizarPuesto($this->repositorio, new ReglasDeCompetenciasDePuesto);
});

it('actualiza el puesto con la versión actual y registra al editor', function () {
    $puesto = $this->actualizar->ejecutar(1, 1, datosDePuesto('Analista senior', 1, 'A'), 9);

    expect($puesto->nombre)->toBe('Analista senior')
        ->and($puesto->version)->toBe(2)
        ->and($puesto->competencias[0]['grado_requerido'])->toBe('A')
        ->and($this->repositorio->ultimoActor)->toBe(9);
});

it('rechaza una versión desactualizada', function () {
    $this->actualizar->ejecutar(1, 1, datosDePuesto('Analista senior', 1, 'A'));

    expect(fn () => $this->actualizar->ejecutar(1, 1, datosDePuesto('Otro nombre', 1, 'A')))->toThrow(PuestoModificado::class);
});

it('guardar sin cambios reales no escribe nada', function () {
    $actual = $this->repositorio->obtener(1);
    $datos = datosDePuesto('Analista', 1, 'B');
    $datos = new DatosPuesto(
        $actual->nombre,
        $actual->descripcion,
        $actual->mision,
        $actual->funciones,
        $actual->requisitos,
        [new AsignacionDeCompetencia(1, 'B')],
    );

    $puesto = $this->actualizar->ejecutar(1, 1, $datos, 9);

    expect($puesto->version)->toBe(1)
        ->and($this->repositorio->escrituras)->toBe(0);
});

it('un puesto inactivo también se puede editar', function () {
    $puesto = $this->actualizar->ejecutar(2, 1, datosDePuesto('Retirado nuevo', 2, 'B'));

    expect($puesto->estado)->toBe('Inactivo')
        ->and($puesto->nombre)->toBe('Retirado nuevo');
});

it('informa cuando el puesto no existe', function () {
    expect(fn () => $this->actualizar->ejecutar(99, 1, datosDePuesto()))->toThrow(PuestoNoEncontrado::class);
});

it('rechaza un código distinto al del puesto y acepta el propio', function () {
    expect(fn () => $this->actualizar->ejecutar(1, 1, datosDePuesto('Analista', 1, 'A'), null, 'PU-999'))->toThrow(CodigoDePuestoNoEditable::class);

    $puesto = $this->actualizar->ejecutar(1, 1, datosDePuesto('Analista', 1, 'A'), null, 'PU-001');
    expect($puesto->codigo)->toBe('PU-001');
});

it('mantiene la competencia inactiva con su grado y rechaza cambiárselo', function () {
    // El puesto 2 ya tiene la competencia 2 (inactiva) con grado B.
    $mantenida = $this->actualizar->ejecutar(2, 1, datosDePuesto('Retirado', 2, 'B'));
    expect($mantenida->competencias[0]['estado'])->toBe('Inactivo');

    expect(fn () => $this->actualizar->ejecutar(2, 1, datosDePuesto('Retirado', 2, 'A')))->toThrow(CompetenciasDePuestoInvalidas::class);
});

it('rechaza agregar una competencia inactiva que el puesto no tenía', function () {
    expect(fn () => $this->actualizar->ejecutar(1, 1, datosDePuesto('Analista', 2, 'B')))->toThrow(CompetenciasDePuestoInvalidas::class);
});

it('rechaza el nombre de otro puesto pero no el propio', function () {
    expect(fn () => $this->actualizar->ejecutar(1, 1, datosDePuesto('retirado', 1, 'B')))->toThrow(NombreDePuestoDuplicado::class);

    $puesto = $this->actualizar->ejecutar(1, 1, datosDePuesto('ANALISTA', 1, 'A'));
    expect($puesto->nombre)->toBe('ANALISTA');
});
