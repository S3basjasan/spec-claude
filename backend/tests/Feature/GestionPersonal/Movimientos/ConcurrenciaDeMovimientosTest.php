<?php

use Illuminate\Support\Facades\DB;

it('dos movimientos incompatibles de la misma persona: solo uno puede quedar aprobado (RMN-CA12)', function () {
    politicaVigente('Ascensos', ['anticipacion_dias' => 0]);
    actuarComoAdminDeTenant();
    $primero = movimientoPresentado($this, ['fecha_efectiva' => hoyDelTenant(3)]);
    $segundo = movimientoPresentado($this, ['fecha_efectiva' => hoyDelTenant(4)], $primero['ficha']);
    evaluadorDeDestino('valida');

    accionApi($this, $primero['id'], 'aprobacion', ['version' => 1, 'justificacion' => 'Primero'])->assertOk();
    accionApi($this, $segundo['id'], 'aprobacion', ['version' => 1, 'justificacion' => 'Segundo'])
        ->assertStatus(409)->assertJsonPath('code', 'MOVIMIENTO_INCOMPATIBLE');

    expect(DB::table('actuaciones_personal')->where('id', $segundo['id'])->value('estado'))->toBe('Presentada')
        ->and(DB::table('actuacion_transiciones')->where('actuacion_id', $segundo['id'])->count())->toBe(1);

    // Lo ya aprobado no se rechaza: cualquier corrección requiere una operación auditada definida aparte.
    accionApi($this, $primero['id'], 'rechazo', ['version' => 2, 'justificacion' => 'Se descarta'])->assertStatus(409);
});

it('el índice de la base impide dos movimientos aprobados pendientes de la misma persona', function () {
    politicaVigente('Ascensos', ['anticipacion_dias' => 0]);
    actuarComoAdminDeTenant();
    $primero = movimientoAprobado($this, ['fecha_efectiva' => hoyDelTenant(3)]);
    $segundo = movimientoPresentado($this, ['fecha_efectiva' => hoyDelTenant(4)], $primero['ficha']);

    expect(fn () => DB::table('actuaciones_personal')->where('id', $segundo['id'])->update(['estado' => 'Programada']))->toThrow(Exception::class)
        ->and(DB::table('actuaciones_personal')->where('id', $segundo['id'])->value('estado'))->toBe('Presentada');
});

it('dos colaboradores al mismo destino: se aplica uno y el otro queda con incidencia, sin ocupación incompatible (RMN-CA12)', function () {
    politicaVigente('Ascensos', ['anticipacion_dias' => 0]);
    actuarComoAdminDeTenant();
    $destino = posicionDeMovimiento();
    $a = movimientoAprobado($this, ['fecha_efectiva' => hoyDelTenant(0)], null, $destino);
    $b = movimientoAprobado($this, ['fecha_efectiva' => hoyDelTenant(0)], null, $destino);

    $this->artisan('movimientos:aplicar-programados')->assertSuccessful();

    $estados = DB::table('actuaciones_personal')->whereIn('id', [$a['id'], $b['id']])->orderBy('id')->pluck('estado')->all();
    expect($estados)->toBe(['Aplicada', 'IncidenciaDeAplicacion'])
        ->and(DB::table('asignaciones_laborales')->where('posicion_id', $destino->id)->whereNull('fecha_fin')->count())->toBe(1)
        ->and(DB::table('asignaciones_laborales')->where('posicion_id', $b['origen']->id)->value('fecha_fin'))->toBeNull()
        ->and(DB::table('movimientos_personal')->where('actuacion_id', $b['id'])->value('incidencia_codigo'))->toBe('DESTINO_OCUPADO');
});

it('una persona con un movimiento aprobado no puede recibir otro efecto que la deje con dos posiciones (US5.9)', function () {
    politicaVigente('Ascensos', ['anticipacion_dias' => 0]);
    actuarComoAdminDeTenant();
    $uno = movimientoAprobado($this, ['fecha_efectiva' => hoyDelTenant(0)]);
    $otroDestino = posicionDeMovimiento();

    // Aun forzando una asignación abierta adicional para la misma persona, la base lo impide.
    expect(fn () => DB::table('asignaciones_laborales')->insert([
        'tenant_id' => 1, 'vinculo_id' => $uno['vinculo'], 'posicion_id' => $otroDestino->id, 'fecha_inicio' => hoyDelTenant(0),
        'fecha_fin' => null, 'version' => 1, 'created_by' => 1, 'updated_by' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]))->toThrow(Exception::class);
    expect(DB::table('asignaciones_laborales')->count())->toBe(1);
});
