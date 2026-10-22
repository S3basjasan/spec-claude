<?php

use App\Modules\Desarrollo\Domain\Entities\NivelDeRuta;
use App\Modules\Desarrollo\Domain\Entities\Ruta;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDeRuta;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoDeRuta;

it('la entidad expone el código, los puestos y los órdenes de sus niveles', function () {
    $ruta = new Ruta(7, 1, 12, 'Ruta', TipoDeRuta::Especialista, null, null, EstadoDeRuta::Borrador, 1, 1, 1, [
        new NivelDeRuta(100, 1, 5, 'Asistente', null),
        new NivelDeRuta(101, 2, 6, 'Analista', 1.5),
    ]);

    expect($ruta->codigo())->toBe('CAR-0012')
        ->and($ruta->idsDePuestos())->toBe([5, 6])
        ->and($ruta->ordenesPorNivel())->toBe([100 => 1, 101 => 2])
        ->and($ruta->ultimoOrden())->toBe(2)
        ->and($ruta->nivelPorId(101)?->nombre)->toBe('Analista')
        ->and($ruta->nivelPorId(999))->toBeNull();
});

it('una ruta sin niveles tiene último orden 0 y sin número no tiene código', function () {
    $ruta = new Ruta(null, 1, null, 'Ruta', TipoDeRuta::Gerencial, null, null, EstadoDeRuta::Borrador, 1, 1, 1);

    expect($ruta->ultimoOrden())->toBe(0)->and($ruta->codigo())->toBeNull()->and($ruta->niveles)->toBe([]);
});

it('un nivel puede cambiar de orden sin perder su identidad', function () {
    $nivel = new NivelDeRuta(100, 1, 5, 'Asistente', 2.0);

    $movido = $nivel->conOrden(3);

    expect($movido->orden)->toBe(3)->and($movido->id)->toBe(100)->and($movido->puestoId)->toBe(5)->and($movido->experienciaMinimaAnios)->toBe(2.0);
});
