<?php

use App\Modules\Compensaciones\Infrastructure\Persistence\Eloquent\Models\ParametroModuloModel;

require_once __DIR__.'/../../Support/Compensaciones/ParametrosHelpers.php';

it('responde 401 sin sesión', function () {
    $this->getJson('/api/compensaciones/parametros')->assertUnauthorized();
    $this->putJson('/api/compensaciones/parametros', ['cambios' => []])->assertUnauthorized();
});

it('responde 403 a quien no es Gestor y no guarda nada', function () {
    actuarComoUsuario();

    $this->getJson('/api/compensaciones/parametros')->assertForbidden();
    putParametro('plazo_anulacion_dias', 10)->assertForbidden();

    expect(ParametroModuloModel::query()->count())->toBe(0);
});

it('permite al Gestor consultar y guardar', function () {
    actuarComoAdmin();

    $this->getJson('/api/compensaciones/parametros')->assertOk()->assertJsonCount(6, 'data');
    putParametro('plazo_anulacion_dias', 10)->assertOk();
});
