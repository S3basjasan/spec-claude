<?php

use App\Modules\Desarrollo\Domain\Entities\NivelDeRuta;
use App\Modules\Desarrollo\Domain\Entities\Ruta;
use App\Modules\Desarrollo\Domain\Services\ReglasDePublicacionDeRuta;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDeRuta;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoDeRuta;

function rutaConPuestos(array $puestoIds): Ruta
{
    $niveles = [];
    foreach ($puestoIds as $i => $puestoId) {
        $niveles[] = new NivelDeRuta($i + 1, $i + 1, $puestoId, 'Nivel '.($i + 1), null);
    }

    return new Ruta(1, 1, 1, 'Ruta', TipoDeRuta::Especialista, null, null, EstadoDeRuta::Borrador, 1, 1, 1, $niveles);
}

it('exige al menos 2 niveles', function () {
    $reglas = new ReglasDePublicacionDeRuta;

    expect($reglas->motivos(rutaConPuestos([]), []))->toBe(['La ruta necesita al menos 2 niveles.'])
        ->and($reglas->motivos(rutaConPuestos([1]), []))->toBe(['La ruta necesita al menos 2 niveles.'])
        ->and($reglas->motivos(rutaConPuestos([1, 2]), []))->toBe([]);
});

it('exige que todos los niveles tengan puesto activo e indica cuáles no', function () {
    $motivos = (new ReglasDePublicacionDeRuta)->motivos(rutaConPuestos([1, 2, 3]), [2, 3]);

    expect($motivos)->toBe(['Estos niveles tienen un puesto inactivo: 2. Nivel 2, 3. Nivel 3.']);
});

it('acumula todos los motivos', function () {
    expect((new ReglasDePublicacionDeRuta)->motivos(rutaConPuestos([1]), [1]))->toHaveCount(2);
});

it('las transiciones de estado siguen Borrador→Vigente→Archivada→Vigente', function () {
    expect(EstadoDeRuta::Borrador->puedePasarA(EstadoDeRuta::Archivada))->toBeFalse()
        ->and(EstadoDeRuta::Archivada->puedePasarA(EstadoDeRuta::Borrador))->toBeFalse()
        ->and(EstadoDeRuta::Vigente->puedePasarA(EstadoDeRuta::Vigente))->toBeFalse();
});
