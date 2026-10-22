<?php

use App\Modules\Desarrollo\Domain\ValueObjects\CodigoDeRuta;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDeRuta;
use App\Modules\Desarrollo\Domain\ValueObjects\NombreDeRuta;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoDeRuta;

it('NombreDeRuta normaliza espacios y mayúsculas para la clave', function () {
    $nombre = new NombreDeRuta("  Ruta   Contable \t ");

    expect($nombre->valor)->toBe('Ruta Contable')
        ->and($nombre->clave())->toBe('ruta contable')
        ->and((new NombreDeRuta(' ruta contable '))->clave())->toBe($nombre->clave());
});

it('NombreDeRuta detecta vacío y exceso de longitud', function () {
    expect((new NombreDeRuta('   '))->esVacio())->toBeTrue()
        ->and((new NombreDeRuta(str_repeat('a', 80)))->excedeElMaximo())->toBeFalse()
        ->and((new NombreDeRuta(str_repeat('a', 81)))->excedeElMaximo())->toBeTrue();
});

it('CodigoDeRuta usa el formato CAR- con cuatro dígitos y rechaza formatos inválidos', function () {
    expect(CodigoDeRuta::desde(1))->toBe('CAR-0001')
        ->and(CodigoDeRuta::desde(12345))->toBe('CAR-12345')
        ->and(CodigoDeRuta::esValido('CAR-0001'))->toBeTrue()
        ->and(CodigoDeRuta::esValido('CAR-1'))->toBeFalse()
        ->and(CodigoDeRuta::esValido('ACT-0001'))->toBeFalse();
});

it('TipoDeRuta::Interareas no admite departamento y los demás sí', function () {
    expect(TipoDeRuta::Interareas->admiteDepartamento())->toBeFalse()
        ->and(TipoDeRuta::Especialista->admiteDepartamento())->toBeTrue()
        ->and(TipoDeRuta::JovenesProfesionales->etiqueta())->toBe('Jóvenes profesionales')
        ->and(TipoDeRuta::Interareas->etiqueta())->toBe('Interáreas');
});

it('EstadoDeRuta solo permite Borrador→Vigente, Vigente→Archivada y Archivada→Vigente', function () {
    expect(EstadoDeRuta::Borrador->puedePasarA(EstadoDeRuta::Vigente))->toBeTrue()
        ->and(EstadoDeRuta::Vigente->puedePasarA(EstadoDeRuta::Archivada))->toBeTrue()
        ->and(EstadoDeRuta::Archivada->puedePasarA(EstadoDeRuta::Vigente))->toBeTrue()
        ->and(EstadoDeRuta::Borrador->puedePasarA(EstadoDeRuta::Archivada))->toBeFalse()
        ->and(EstadoDeRuta::Vigente->puedePasarA(EstadoDeRuta::Borrador))->toBeFalse()
        ->and(EstadoDeRuta::Archivada->puedePasarA(EstadoDeRuta::Borrador))->toBeFalse();
});
