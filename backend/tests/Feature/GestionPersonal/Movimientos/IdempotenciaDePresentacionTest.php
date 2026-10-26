<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('la misma confirmación repetida devuelve el resultado anterior sin duplicar (US1.8, RMN-CA12)', function () {
    politicaVigente('Permisos');
    actuarComoAdminDeTenant();
    ['ficha' => $ficha] = fichaConVinculo();
    $clave = (string) Str::uuid();
    $cuerpo = datosNovedad($ficha->id);

    $primera = presentarNovedadApi($this, $cuerpo, $clave)->assertCreated();
    $segunda = presentarNovedadApi($this, $cuerpo, $clave)->assertOk();

    expect($segunda->json('data.id'))->toBe($primera->json('data.id'))
        ->and(DB::table('actuaciones_personal')->count())->toBe(1)
        ->and(DB::table('actuacion_transiciones')->count())->toBe(1);
});

it('reutilizar la clave con otro contenido o con otro actor es un conflicto y no crea nada', function () {
    politicaVigente('Permisos');
    actuarComoAdminDeTenant();
    ['ficha' => $ficha] = fichaConVinculo();
    $clave = (string) Str::uuid();
    presentarNovedadApi($this, datosNovedad($ficha->id), $clave)->assertCreated();

    presentarNovedadApi($this, datosNovedad($ficha->id, ['motivo' => 'Otro motivo']), $clave)
        ->assertStatus(409)->assertJsonPath('code', 'CLAVE_DE_OPERACION_REUTILIZADA');

    actuarComoAdminDeTenant();
    presentarNovedadApi($this, datosNovedad($ficha->id), $clave)
        ->assertStatus(409)->assertJsonPath('code', 'CLAVE_DE_OPERACION_REUTILIZADA');

    expect(DB::table('actuaciones_personal')->count())->toBe(1);
});

it('exige una Idempotency-Key con formato de UUID', function () {
    politicaVigente('Permisos');
    actuarComoAdminDeTenant();
    ['ficha' => $ficha] = fichaConVinculo();

    $this->postJson(URL_MOVIMIENTOS.'/novedades', datosNovedad($ficha->id))
        ->assertStatus(422)->assertJsonPath('code', 'CLAVE_DE_OPERACION_REQUERIDA');
    $this->postJson(URL_MOVIMIENTOS.'/novedades', datosNovedad($ficha->id), ['Idempotency-Key' => 'no-es-uuid'])
        ->assertStatus(422)->assertJsonPath('code', 'CLAVE_DE_OPERACION_REQUERIDA');
    expect(DB::table('actuaciones_personal')->count())->toBe(0);
});

it('la clave de una presentación no sirve para resolver otra actuación', function () {
    politicaVigente('Permisos');
    actuarComoAdminDeTenant();
    ['ficha' => $ficha] = fichaConVinculo();
    $clave = (string) Str::uuid();
    $id = presentarNovedadApi($this, datosNovedad($ficha->id), $clave)->json('data.id');

    accionApi($this, $id, 'revision', ['version' => 1], $clave)
        ->assertStatus(409)->assertJsonPath('code', 'CLAVE_DE_OPERACION_REUTILIZADA');
    expect(DB::table('actuaciones_personal')->value('estado'))->toBe('Presentada');
});

it('dos presentaciones con la misma clave a la vez dejan una sola solicitud', function () {
    politicaVigente('Permisos');
    actuarComoAdminDeTenant();
    ['ficha' => $ficha] = fichaConVinculo();
    $clave = (string) Str::uuid();
    $cuerpo = datosNovedad($ficha->id);

    // La segunda llega cuando la primera ya confirmó: se reconoce por la clave dentro del bloqueo o antes de él.
    $resultados = [presentarNovedadApi($this, $cuerpo, $clave), presentarNovedadApi($this, $cuerpo, $clave)];

    expect(collect($resultados)->map->status()->sort()->values()->all())->toBe([200, 201])
        ->and(DB::table('actuaciones_personal')->count())->toBe(1);
});
