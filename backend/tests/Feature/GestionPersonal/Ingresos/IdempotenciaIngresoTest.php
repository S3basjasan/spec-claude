<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('la misma clave y cuerpo devuelve 200 con el resultado de la primera y un solo vínculo', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe(['numero_identificacion' => '90']);
    $posicion = posicionAsignable();
    $clave = (string) Str::uuid();

    $primera = registrarIngresoApi($this, $ficha->id, datosIngreso($posicion->id), $clave)->assertCreated();
    $segunda = registrarIngresoApi($this, $ficha->id, datosIngreso($posicion->id), $clave)->assertOk();

    expect($segunda->json('data.vinculo.id'))->toBe($primera->json('data.vinculo.id'))
        ->and($segunda->json('data.asignacion.id'))->toBe($primera->json('data.asignacion.id'))
        ->and(DB::table('vinculos_laborales')->count())->toBe(1)
        ->and(DB::table('asignaciones_laborales')->count())->toBe(1);
});

it('la misma clave con otros datos u otro actor devuelve 409 sin crear nada', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe(['numero_identificacion' => '91']);
    $posicion = posicionAsignable();
    $otra = posicionAsignable();
    $clave = (string) Str::uuid();

    registrarIngresoApi($this, $ficha->id, datosIngreso($posicion->id), $clave)->assertCreated();
    registrarIngresoApi($this, $ficha->id, datosIngreso($otra->id), $clave)
        ->assertStatus(409)->assertJsonPath('code', 'CLAVE_DE_OPERACION_REUTILIZADA');

    actuarComoAdminDeTenant();
    registrarIngresoApi($this, $ficha->id, datosIngreso($posicion->id), $clave)
        ->assertStatus(409);

    expect(DB::table('vinculos_laborales')->count())->toBe(1);
});

it('exige una clave de operación válida', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe(['numero_identificacion' => '92']);
    $posicion = posicionAsignable();

    $this->postJson("/api/colaboradores/{$ficha->id}/ingresos", datosIngreso($posicion->id))
        ->assertUnprocessable()->assertJsonPath('code', 'CLAVE_DE_OPERACION_REQUERIDA');
    $this->postJson("/api/colaboradores/{$ficha->id}/ingresos", datosIngreso($posicion->id), ['Idempotency-Key' => 'no-uuid'])
        ->assertUnprocessable()->assertJsonPath('code', 'CLAVE_DE_OPERACION_REQUERIDA');

    expect(DB::table('vinculos_laborales')->count())->toBe(0);
});

it('una repetición responde 200 aunque la posición ya esté ocupada por la propia operación', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe(['numero_identificacion' => '93']);
    $posicion = posicionAsignable();
    $clave = (string) Str::uuid();

    registrarIngresoApi($this, $ficha->id, datosIngreso($posicion->id), $clave)->assertCreated();

    // Sin la clave, el mismo cuerpo se rechaza por superposición; con ella es una repetición válida.
    registrarIngresoApi($this, $ficha->id, datosIngreso($posicion->id))->assertUnprocessable();
    registrarIngresoApi($this, $ficha->id, datosIngreso($posicion->id), $clave)->assertOk();
});
