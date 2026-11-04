<?php

require_once __DIR__.'/../../Support/Compensaciones/ParametrosHelpers.php';

it('acepta umbrales de 1 a 100 y rechaza 0 y 101', function (string $clave) {
    actuarComoAdmin();

    putParametro($clave, 1)->assertOk();
    putParametro($clave, 100, 1)->assertOk();
    putParametro($clave, 0, 2)->assertUnprocessable();
    putParametro($clave, 101, 2)->assertUnprocessable();

    $datos = $this->getJson('/api/compensaciones/parametros')->json();
    expect(porClave($datos, $clave)['valor'])->toBe(100);
})->with(['umbral_concentracion', 'umbral_clima_unidad']);

it('acepta la sensibilidad 1, 2 o 3 y rechaza 0 y 4', function () {
    actuarComoAdmin();

    putParametro('sensibilidad_radar', 1)->assertOk();
    putParametro('sensibilidad_radar', 3, 1)->assertOk();
    putParametro('sensibilidad_radar', 0, 2)->assertUnprocessable();
    putParametro('sensibilidad_radar', 4, 2)->assertUnprocessable();

    $datos = $this->getJson('/api/compensaciones/parametros')->json();
    expect(porClave($datos, 'sensibilidad_radar')['valor'])->toBe(3);
});

it('rechaza una clave desconocida', function () {
    actuarComoAdmin();

    putParametro('otra_clave', 5)->assertUnprocessable()->assertJsonValidationErrors(['cambios.0.clave']);
});
