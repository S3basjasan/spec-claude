<?php

use App\Modules\Estructura\Application\UseCases\CrearPuesto;
use App\Modules\Estructura\Application\UseCases\ListarCompetenciasParaPuestos;
use App\Modules\Estructura\Domain\Exceptions\CompetenciasDePuestoInvalidas;
use App\Modules\Estructura\Domain\Exceptions\NombreDePuestoDuplicado;
use App\Modules\Estructura\Domain\Services\ReglasDeCompetenciasDePuesto;
use App\Modules\Estructura\Domain\ValueObjects\EstadoCompetencia;
use Tests\Unit\Estructura\Puestos\RepositorioPuestosEnMemoria;

beforeEach(function () {
    $this->repositorio = new RepositorioPuestosEnMemoria;
    $this->repositorio->agregarCompetencia('Liderazgo');
    $this->repositorio->agregarCompetencia('Adaptabilidad', EstadoCompetencia::Inactivo);
    $this->crear = new CrearPuesto($this->repositorio, new ReglasDeCompetenciasDePuesto);
});

it('crea el puesto y devuelve su detalle con el actor registrado', function () {
    $puesto = $this->crear->ejecutar(datosDePuesto(), 7);

    expect($puesto->nombre)->toBe('Analista')
        ->and($puesto->estado)->toBe('Activo')
        ->and($puesto->version)->toBe(1)
        ->and($puesto->funciones)->toHaveCount(1)
        ->and($puesto->competencias[0]['grado_requerido'])->toBe('B')
        ->and($this->repositorio->ultimoActor)->toBe(7)
        ->and($this->repositorio->escrituras)->toBe(1);
});

it('rechaza una competencia inactiva o inexistente sin escribir nada', function () {
    expect(fn () => $this->crear->ejecutar(datosDePuesto(competenciaId: 2)))->toThrow(CompetenciasDePuestoInvalidas::class)
        ->and(fn () => $this->crear->ejecutar(datosDePuesto(competenciaId: 9)))->toThrow(CompetenciasDePuestoInvalidas::class)
        ->and($this->repositorio->escrituras)->toBe(0);
});

it('rechaza un grado que la competencia no define', function () {
    expect(fn () => $this->crear->ejecutar(datosDePuesto(grado: 'Z')))->toThrow(CompetenciasDePuestoInvalidas::class)
        ->and($this->repositorio->escrituras)->toBe(0);
});

it('propaga el nombre duplicado', function () {
    $this->crear->ejecutar(datosDePuesto('Analista'));

    expect(fn () => $this->crear->ejecutar(datosDePuesto('analista')))->toThrow(NombreDePuestoDuplicado::class);
});

it('lista las competencias activas con sus grados para el formulario', function () {
    $competencias = (new ListarCompetenciasParaPuestos($this->repositorio))->ejecutar();

    expect($competencias)->toHaveCount(1)
        ->and($competencias[0]->nombre)->toBe('Liderazgo')
        ->and(array_column($competencias[0]->grados, 'grado'))->toBe(['A', 'B', 'C', 'D']);
});
