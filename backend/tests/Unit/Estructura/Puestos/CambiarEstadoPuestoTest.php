<?php

use App\Modules\Estructura\Application\UseCases\CambiarEstadoPuesto;
use App\Modules\Estructura\Domain\Exceptions\PuestoConPosicionesEnUso;
use App\Modules\Estructura\Domain\Exceptions\PuestoNoEncontrado;
use App\Modules\Estructura\Domain\ValueObjects\EstadoPuesto;
use Tests\Unit\Estructura\Puestos\RepositorioPuestosEnMemoria;

beforeEach(function () {
    $this->repositorio = new RepositorioPuestosEnMemoria;
    $this->repositorio->agregarPuesto('Analista');
    $this->repositorio->agregarPuesto('Jefe');
    $this->repositorio->posicionesEnUso[2] = 3;
    $this->repositorio->agregarPuesto('Retirado', EstadoPuesto::Inactivo);
    $this->cambiar = new CambiarEstadoPuesto($this->repositorio);
});

it('desactiva un puesto sin posiciones en uso y registra al actor', function () {
    $puesto = $this->cambiar->ejecutar(1, 'Inactivo', 9);

    expect($puesto->estado)->toBe('Inactivo')
        ->and($this->repositorio->ultimoActor)->toBe(9);
});

it('rechaza desactivar un puesto con posiciones en uso e informa cuántas', function () {
    try {
        $this->cambiar->ejecutar(2, 'Inactivo');
        $this->fail('Debía rechazar la desactivación.');
    } catch (PuestoConPosicionesEnUso $e) {
        expect($e->cantidad)->toBe(3)
            ->and($e->getMessage())->toBe('No se puede desactivar el puesto: tiene 3 posiciones ocupadas o vacantes.');
    }

    expect($this->repositorio->obtener(2)->estado)->toBe(EstadoPuesto::Activo);
});

it('reactiva sin restricciones', function () {
    expect($this->cambiar->ejecutar(3, 'Activo')->estado)->toBe('Activo');
});

it('si ya está en ese estado no cambia nada', function () {
    $this->cambiar->ejecutar(3, 'Inactivo');

    expect($this->repositorio->escrituras)->toBe(0);
});

it('informa cuando el puesto no existe', function () {
    expect(fn () => $this->cambiar->ejecutar(99, 'Inactivo'))->toThrow(PuestoNoEncontrado::class);
});
