<?php

use Illuminate\Support\Facades\DB;

it('rechaza una posición ocupada en el periodo, existente o futura, sin filas parciales (GP02-CA04)', function () {
    actuarComoAdminDeTenant();
    $ocupante = fichaDe(['numero_identificacion' => '40']);
    $nuevo = fichaDe(['numero_identificacion' => '41']);
    $posicion = posicionAsignable();
    vinculoDe($ocupante->id, hoyDelTenant(-30), null, $posicion->id);

    registrarIngresoApi($this, $nuevo->id, datosIngreso($posicion->id))
        ->assertUnprocessable()->assertJsonPath('code', 'POSICION_NO_DISPONIBLE')->assertJsonValidationErrors(['posicion_id']);

    expect(DB::table('vinculos_laborales')->where('colaborador_id', $nuevo->id)->count())->toBe(0)
        ->and(DB::table('asignaciones_laborales')->count())->toBe(1);

    // Ocupación futura ya programada: aunque hoy esté libre, el ingreso abierto la alcanzaría.
    $otraPosicion = posicionAsignable();
    vinculoDe(fichaDe(['numero_identificacion' => '42'])->id, hoyDelTenant(60), null, $otraPosicion->id);

    registrarIngresoApi($this, $nuevo->id, datosIngreso($otraPosicion->id))
        ->assertUnprocessable()->assertJsonPath('code', 'POSICION_NO_DISPONIBLE');
});

it('rechaza un vínculo superpuesto de la ficha aunque se elija otra posición (GP02-CA07)', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe(['numero_identificacion' => '43']);
    vinculoDe($ficha->id, hoyDelTenant(-10), null, posicionAsignable()->id);
    $libre = posicionAsignable();

    registrarIngresoApi($this, $ficha->id, datosIngreso($libre->id))
        ->assertUnprocessable()->assertJsonPath('code', 'VINCULO_SUPERPUESTO')->assertJsonValidationErrors(['fecha_ingreso']);

    // También con un vínculo programado.
    $otra = fichaDe(['numero_identificacion' => '44']);
    vinculoDe($otra->id, hoyDelTenant(30), null, posicionAsignable()->id);
    registrarIngresoApi($this, $otra->id, datosIngreso(posicionAsignable()->id))
        ->assertUnprocessable()->assertJsonPath('code', 'VINCULO_SUPERPUESTO');

    expect(DB::table('vinculos_laborales')->count())->toBe(2);
});

it('acepta intervalos adyacentes: ocupante que sale el día en que entra el nuevo', function () {
    actuarComoAdminDeTenant();
    $ocupante = fichaDe(['numero_identificacion' => '45']);
    $nuevo = fichaDe(['numero_identificacion' => '46']);
    $posicion = posicionAsignable();
    $salida = hoyDelTenant(10);
    vinculoDe($ocupante->id, hoyDelTenant(-100), $salida, $posicion->id);

    registrarIngresoApi($this, $nuevo->id, datosIngreso($posicion->id, ['fecha_ingreso' => $salida]))->assertCreated();
    registrarIngresoApi($this, fichaDe(['numero_identificacion' => '47'])->id, datosIngreso($posicion->id, ['fecha_ingreso' => hoyDelTenant(9)]))
        ->assertUnprocessable()->assertJsonPath('code', 'POSICION_NO_DISPONIBLE');
});

it('rechaza posición suprimida, con puesto inactivo o con unidad inactiva', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe(['numero_identificacion' => '48']);

    $casos = [
        posicionAsignable(['estado' => 'Suprimida']),
        posicionAsignable(['puesto_id' => puestoDe(['estado' => 'Inactivo'])->id]),
        posicionAsignable(['unidad_organizacional_id' => unidadDe(['estado' => 'Inactiva'])->id]),
    ];

    foreach ($casos as $posicion) {
        registrarIngresoApi($this, $ficha->id, datosIngreso($posicion->id))
            ->assertUnprocessable()->assertJsonPath('code', 'POSICION_NO_DISPONIBLE');
    }

    expect(DB::table('vinculos_laborales')->count())->toBe(0);
});

it('dos fichas distintas pueden ingresar a posiciones distintas del mismo puesto', function () {
    actuarComoAdminDeTenant();
    $puesto = puestoDe();
    $uno = posicionAsignable(['puesto_id' => $puesto->id]);
    $dos = posicionAsignable(['puesto_id' => $puesto->id]);

    registrarIngresoApi($this, fichaDe(['numero_identificacion' => '49'])->id, datosIngreso($uno->id))->assertCreated();
    registrarIngresoApi($this, fichaDe(['numero_identificacion' => '50'])->id, datosIngreso($dos->id))->assertCreated();
});

it('un ingreso con fecha anterior a un vínculo cerrado que se solapa se rechaza', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe(['numero_identificacion' => '51']);
    vinculoDe($ficha->id, hoyDelTenant(-200), hoyDelTenant(-100), posicionAsignable()->id);

    registrarIngresoApi($this, $ficha->id, datosIngreso(posicionAsignable()->id, ['fecha_ingreso' => hoyDelTenant(-150)]))
        ->assertUnprocessable()->assertJsonPath('code', 'VINCULO_SUPERPUESTO');
});
