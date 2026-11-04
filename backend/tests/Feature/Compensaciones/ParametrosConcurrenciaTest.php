<?php

use App\Modules\Compensaciones\Infrastructure\Persistence\Eloquent\Models\ParametroModuloModel;

require_once __DIR__.'/../../Support/Compensaciones/ParametrosHelpers.php';

it('responde 409 con una version desactualizada y no guarda nada', function () {
    actuarComoAdmin();
    putParametro('plazo_anulacion_dias', 10)->assertOk();

    $this->putJson('/api/compensaciones/parametros', ['cambios' => [
        ['clave' => 'plazo_anulacion_dias', 'valor' => 12, 'version' => 0],
        ['clave' => 'umbral_concentracion', 'valor' => 60, 'version' => 0],
    ]])
        ->assertStatus(409)
        ->assertJsonPath('message', 'Otra persona cambió un parámetro mientras editabas. Recarga para ver los valores actuales.');

    expect(ParametroModuloModel::query()->count())->toBe(1);
});

it('la segunda primera edición de una clave que nunca cambió recibe 409', function () {
    actuarComoAdmin();

    putParametro('plazo_anulacion_dias', 10, 0)->assertOk();
    putParametro('plazo_anulacion_dias', 12, 0)->assertStatus(409);

    expect(ParametroModuloModel::query()->count())->toBe(1);
});

it('acepta el cambio cuando la version es la actual', function () {
    actuarComoAdmin();
    putParametro('plazo_anulacion_dias', 10)->assertOk();
    $version = ParametroModuloModel::query()->max('id');

    putParametro('plazo_anulacion_dias', 12, $version)->assertOk();

    expect(ParametroModuloModel::query()->count())->toBe(2);
});

it('exige la version en cada cambio', function () {
    actuarComoAdmin();

    $this->putJson('/api/compensaciones/parametros', ['cambios' => [['clave' => 'plazo_anulacion_dias', 'valor' => 10]]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['cambios.0.version']);
});
