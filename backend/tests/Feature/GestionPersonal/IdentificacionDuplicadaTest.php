<?php

use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\ColaboradorModel;

it('rechaza una identificación repetida con espacios exteriores y ofrece la ficha existente (GP01-CA02)', function () {
    actuarComoAdminDeTenant();
    $primera = crearFichaApi($this, datosFicha(['numero_identificacion' => '0102030405']))->assertCreated();

    crearFichaApi($this, datosFicha(['numero_identificacion' => ' 0102030405 ', 'nombres' => 'Otra']))
        ->assertUnprocessable()
        ->assertJsonPath('code', 'IDENTIFICACION_DUPLICADA')
        ->assertJsonPath('ficha_existente.id', $primera->json('data.id'))
        ->assertJsonPath('ficha_existente.codigo', $primera->json('data.codigo'))
        ->assertJsonValidationErrors(['numero_identificacion']);

    expect(ColaboradorModel::query()->count())->toBe(1);
});

it('no distingue mayúsculas pero sí el tipo de identificación', function () {
    actuarComoAdminDeTenant();
    crearFichaApi($this, datosFicha(['tipo_identificacion' => 'Pasaporte', 'numero_identificacion' => 'AB123']))->assertCreated();

    crearFichaApi($this, datosFicha(['tipo_identificacion' => 'Pasaporte', 'numero_identificacion' => 'ab123']))
        ->assertUnprocessable()->assertJsonPath('code', 'IDENTIFICACION_DUPLICADA');
    crearFichaApi($this, datosFicha(['tipo_identificacion' => 'Cedula', 'numero_identificacion' => 'AB123']))->assertCreated();
    crearFichaApi($this, datosFicha(['tipo_identificacion' => 'Otro', 'numero_identificacion' => 'AB123']))->assertCreated();

    expect(ColaboradorModel::query()->count())->toBe(3);
});

it('conserva ceros iniciales y separadores internos', function () {
    actuarComoAdminDeTenant();
    crearFichaApi($this, datosFicha(['numero_identificacion' => '0102-03']))->assertCreated();

    crearFichaApi($this, datosFicha(['numero_identificacion' => '010203']))->assertCreated();
    crearFichaApi($this, datosFicha(['numero_identificacion' => '  0102-03 ']))->assertUnprocessable();
    crearFichaApi($this, datosFicha(['numero_identificacion' => '102-03']))->assertCreated();
});

it('acepta la misma identificación en dos tenants', function () {
    actuarComoAdminDeTenant(1);
    crearFichaApi($this, datosFicha())->assertCreated();

    actuarComoAdminDeTenant(2);
    $dos = crearFichaApi($this, datosFicha())->assertCreated();

    expect(ColaboradorModel::query()->where('tenant_id', 2)->count())->toBe(1)
        ->and($dos->json('data.codigo'))->toMatch('/^EMP-\d{3,}$/');
});

it('con claves distintas y la misma identificación solo se guarda una ficha', function () {
    actuarComoAdminDeTenant();

    crearFichaApi($this, datosFicha())->assertCreated();
    crearFichaApi($this, datosFicha(['nombres' => 'Gemela']))->assertUnprocessable();

    expect(ColaboradorModel::query()->count())->toBe(1);
});
