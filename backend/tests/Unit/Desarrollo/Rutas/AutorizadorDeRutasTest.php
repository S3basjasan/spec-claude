<?php

use App\Modules\Desarrollo\Application\Services\AutorizadorDeRutas;
use App\Modules\Desarrollo\Domain\ValueObjects\ClaveDePermisoRutas;

it('el administrador tiene todas las claves salvo las terminadas en _propio', function () {
    $autorizador = new AutorizadorDeRutas;

    foreach (ClaveDePermisoRutas::cases() as $clave) {
        expect($autorizador->permite(true, $clave))->toBe($clave !== ClaveDePermisoRutas::VerPropio);
    }
});

it('el colaborador solo tiene las claves terminadas en _propio', function () {
    $autorizador = new AutorizadorDeRutas;

    foreach (ClaveDePermisoRutas::cases() as $clave) {
        expect($autorizador->permite(false, $clave))->toBe(str_ends_with($clave->value, '_propio'));
    }
});

it('las claves son estables', function () {
    expect(array_map(fn ($c) => $c->value, ClaveDePermisoRutas::cases()))->toBe([
        'desarrollo.rutas.ver', 'desarrollo.rutas.gestionar', 'desarrollo.rutas.inscribir',
        'desarrollo.rutas.inscripciones', 'desarrollo.rutas.historial', 'desarrollo.rutas.ver_propio',
    ]);
});
