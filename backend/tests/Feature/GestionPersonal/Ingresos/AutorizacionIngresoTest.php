<?php

use Illuminate\Support\Facades\DB;

it('exige sesión y deniega al Usuario en el alta y en las opciones', function () {
    $ficha = fichaDe(['numero_identificacion' => '20']);
    $posicion = posicionAsignable();

    $this->postJson("/api/colaboradores/{$ficha->id}/ingresos", datosIngreso($posicion->id))->assertUnauthorized();
    $this->getJson("/api/colaboradores/{$ficha->id}/opciones-ingreso?fecha=2026-10-15")->assertUnauthorized();

    actuarComoUsuarioDeTenant();
    registrarIngresoApi($this, $ficha->id, datosIngreso($posicion->id))->assertForbidden();
    $this->getJson("/api/colaboradores/{$ficha->id}/opciones-ingreso?fecha=2026-10-15")->assertForbidden();

    expect(DB::table('vinculos_laborales')->count())->toBe(0);
});

it('una ficha de otro tenant responde igual que una inexistente', function () {
    $ajena = fichaDe(['numero_identificacion' => '21'], 2);
    $posicion = posicionAsignable();
    actuarComoAdminDeTenant();

    $a = registrarIngresoApi($this, $ajena->id, datosIngreso($posicion->id))->assertNotFound();
    $b = registrarIngresoApi($this, 999999, datosIngreso($posicion->id))->assertNotFound();

    expect($a->json())->toBe($b->json())
        ->and(DB::table('vinculos_laborales')->count())->toBe(0);
});

it('una posición de otro tenant no es asignable', function () {
    $ficha = fichaDe(['numero_identificacion' => '22']);
    $ajena = posicionAsignable([], 2);
    actuarComoAdminDeTenant();

    registrarIngresoApi($this, $ficha->id, datosIngreso($ajena->id))
        ->assertUnprocessable()->assertJsonPath('code', 'POSICION_NO_DISPONIBLE');

    expect(DB::table('vinculos_laborales')->count())->toBe(0);
});

it('con ingresos_habilitados en false responde 503 y no crea nada', function () {
    config(['hrms.gestion_personal.ingresos_habilitados' => false]);
    $ficha = fichaDe(['numero_identificacion' => '23']);
    $posicion = posicionAsignable();
    actuarComoAdminDeTenant();

    registrarIngresoApi($this, $ficha->id, datosIngreso($posicion->id))
        ->assertStatus(503)->assertJsonPath('code', 'INGRESOS_DESHABILITADOS');

    expect(DB::table('vinculos_laborales')->count())->toBe(0);
});
