<?php

use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\ColaboradorCuentaModel;

it('el Administrador consulta situación laboral y trayectoria con puesto, unidad y contrato', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe(['numero_identificacion' => '100']);
    $posicion = posicionAsignable();
    vinculoDe($ficha->id, hoyDelTenant(-400), hoyDelTenant(-200), posicionAsignable()->id, null, 1, 'temporal');
    vinculoDe($ficha->id, hoyDelTenant(-100), null, $posicion->id, null, 1, 'plazo_fijo');

    $situacion = $this->getJson("/api/colaboradores/{$ficha->id}/situacion-laboral")->assertOk();
    $trayectoria = $this->getJson("/api/colaboradores/{$ficha->id}/trayectoria")->assertOk();

    expect($situacion->json('data.estado'))->toBe('Activo')
        ->and($situacion->json('data.vinculo_vigente.tipo_contrato'))->toBe(['clave' => 'plazo_fijo', 'etiqueta' => 'Plazo fijo'])
        ->and($situacion->json('data.vinculo_vigente.posicion.id'))->toBe($posicion->id)
        ->and($situacion->json('data.vinculo_vigente.posicion.puesto'))->not->toBeNull()
        ->and($situacion->json('data.vinculo_vigente.posicion.unidad'))->not->toBeNull()
        ->and($situacion->json('data.vinculo_vigente.posicion.jefatura'))->toBeNull()
        ->and(array_column($trayectoria->json('data'), 'tipo'))->toBe(['Reingreso', 'Ingreso'])
        ->and(array_column($trayectoria->json('data'), 'fecha_efectiva'))->toBe([hoyDelTenant(-100), hoyDelTenant(-400)]);
});

it('una ficha sin vínculos tiene trayectoria vacía y estado Sin vínculo vigente', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe(['numero_identificacion' => '101']);

    expect($this->getJson("/api/colaboradores/{$ficha->id}/situacion-laboral")->json('data.estado'))->toBe('SinVinculoVigente')
        ->and($this->getJson("/api/colaboradores/{$ficha->id}/trayectoria")->json('data'))->toBe([]);
});

it('el Colaborador consulta solo su situación y trayectoria, nunca las de otra persona', function () {
    $usuario = actuarComoUsuarioDeTenant();
    $propia = fichaDe(['numero_identificacion' => '102']);
    $ajena = fichaDe(['numero_identificacion' => '103']);
    ColaboradorCuentaModel::query()->create([
        'tenant_id' => 1, 'user_id' => $usuario->id, 'colaborador_id' => $propia->id, 'puede_consultar_ficha' => true,
    ]);
    vinculoDe($propia->id, hoyDelTenant(-10), null, posicionAsignable()->id);
    vinculoDe($ajena->id, hoyDelTenant(-10), null, posicionAsignable()->id);

    $this->getJson('/api/mi-ficha/situacion-laboral')->assertOk()->assertJsonPath('data.estado', 'Activo');
    $this->getJson('/api/mi-ficha/trayectoria')->assertOk()->assertJsonCount(1, 'data');

    $this->getJson("/api/colaboradores/{$ajena->id}/situacion-laboral")->assertForbidden();
    $this->getJson("/api/colaboradores/{$ajena->id}/trayectoria")->assertForbidden();
    $this->getJson("/api/colaboradores/{$propia->id}/situacion-laboral")->assertForbidden();
});

it('mi-ficha informa cuenta sin ficha y falta de permiso', function () {
    $sinFicha = actuarComoUsuarioDeTenant();
    $this->getJson('/api/mi-ficha/situacion-laboral')->assertForbidden()->assertJsonPath('code', 'CUENTA_SIN_FICHA');
    $this->getJson('/api/mi-ficha/trayectoria')->assertForbidden()->assertJsonPath('code', 'CUENTA_SIN_FICHA');

    $conAcceso = actuarComoUsuarioDeTenant();
    ColaboradorCuentaModel::query()->create([
        'tenant_id' => 1, 'user_id' => $conAcceso->id, 'colaborador_id' => fichaDe(['numero_identificacion' => '104'])->id,
        'puede_consultar_ficha' => false,
    ]);
    $this->getJson('/api/mi-ficha/situacion-laboral')->assertForbidden()->assertJsonPath('code', 'SIN_PERMISO_FICHA');
    expect($sinFicha->id)->not->toBe($conAcceso->id);
});

it('la trayectoria muestra el nombre vigente del puesto, no una definición congelada', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe(['numero_identificacion' => '105']);
    $puesto = puestoDe(['nombre' => 'Analista inicial']);
    $posicion = posicionAsignable(['puesto_id' => $puesto->id]);
    vinculoDe($ficha->id, hoyDelTenant(-10), null, $posicion->id);

    $puesto->forceFill(['nombre' => 'Analista senior'])->save();

    $hecho = $this->getJson("/api/colaboradores/{$ficha->id}/trayectoria")->json('data.0');
    expect($hecho['posicion']['puesto'])->toBe('Analista senior');
});

it('una ficha de otro tenant responde 404 en ambas lecturas', function () {
    actuarComoAdminDeTenant();
    $ajena = fichaDe(['numero_identificacion' => '106'], 2);

    $this->getJson("/api/colaboradores/{$ajena->id}/situacion-laboral")->assertNotFound();
    $this->getJson("/api/colaboradores/{$ajena->id}/trayectoria")->assertNotFound();
});
