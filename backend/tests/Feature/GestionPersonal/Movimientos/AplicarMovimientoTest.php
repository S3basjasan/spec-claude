<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

afterEach(fn () => Carbon::setTestNow());

it('aprobar un movimiento futuro lo programa sin cambiar la posición, y el Colaborador solo ve resultado y fecha (US5.1, RMN-CA03)', function () {
    politicaVigente('Ascensos', ['anticipacion_dias' => 5]);
    actuarComoAdminDeTenant();
    $movimiento = movimientoPresentado($this);
    evaluadorDeDestino('valida');
    $antes = DB::table('asignaciones_laborales')->orderBy('id')->get()->toArray();

    $aprobada = accionApi($this, $movimiento['id'], 'aprobacion', ['version' => 1, 'justificacion' => 'Cumple los requisitos'])->assertOk();

    expect($aprobada->json('data.estado'))->toBe('Programada')
        ->and($aprobada->json('data.movimiento.aplicacion.estado'))->toBe('Pendiente')
        ->and($aprobada->json('data.acciones.aplicar'))->toBeFalse()
        ->and(DB::table('asignaciones_laborales')->orderBy('id')->get()->toArray())->toEqual($antes);

    actuarComoColaboradorDe($movimiento['ficha']);
    $vista = $this->getJson(URL_MIS_SOLICITUDES.'/'.$movimiento['id'])->assertOk()->json('data');
    expect($vista)->toBe(['id' => $movimiento['id'], 'tipo' => 'Ascenso', 'categoria' => 'Movimiento', 'estado' => 'Programada', 'fecha_efectiva' => hoyDelTenant(30)])
        ->and($this->getJson(URL_MIS_SOLICITUDES)->json('data'))->toHaveCount(1);
});

it('no se aplica antes de su fecha efectiva ni por presentarlo (US5.7)', function () {
    politicaVigente('Ascensos', ['anticipacion_dias' => 5]);
    actuarComoAdminDeTenant();
    $presentado = movimientoPresentado($this);
    $aprobado = movimientoAprobado($this);

    accionApi($this, $aprobado['id'], 'aplicacion', ['version' => 2])->assertStatus(409)->assertJsonPath('code', 'APLICACION_NO_PROCEDE');
    accionApi($this, $presentado['id'], 'aplicacion', ['version' => 1])->assertStatus(409)->assertJsonPath('code', 'APLICACION_NO_PROCEDE');

    Carbon::setTestNow(now()->addDays(31));
    $this->artisan('movimientos:aplicar-programados')->assertSuccessful();

    expect(DB::table('actuaciones_personal')->where('id', $presentado['id'])->value('estado'))->toBe('Presentada')
        ->and(DB::table('actuaciones_personal')->where('id', $aprobado['id'])->value('estado'))->toBe('Aplicada')
        ->and(DB::table('asignaciones_laborales')->where('posicion_id', $presentado['destino']->id)->count())->toBe(0);
});

it('llegada la fecha, el sistema cierra la asignación anterior y abre la nueva, y queda el hecho laboral (US5.2)', function () {
    politicaVigente('Ascensos', ['anticipacion_dias' => 5]);
    actuarComoAdminDeTenant();
    $movimiento = movimientoAprobado($this);
    $fecha = hoyDelTenant(30);
    $ingreso = DB::table('vinculos_laborales')->first();

    Carbon::setTestNow(now()->addDays(31));
    $this->artisan('movimientos:aplicar-programados')->expectsOutputToContain('Aplicados: 1')->assertSuccessful();

    $origen = DB::table('asignaciones_laborales')->where('posicion_id', $movimiento['origen']->id)->first();
    $nueva = DB::table('asignaciones_laborales')->where('posicion_id', $movimiento['destino']->id)->first();
    $detalle = $this->getJson(URL_MOVIMIENTOS.'/'.$movimiento['id'])->assertOk();

    expect($origen->fecha_fin)->toBe($fecha)
        ->and($nueva->fecha_inicio)->toBe($fecha)->and($nueva->fecha_fin)->toBeNull()
        ->and($nueva->actuacion_id)->toBe($movimiento['id'])
        ->and($nueva->vinculo_id)->toBe($origen->vinculo_id)
        ->and(DB::table('vinculos_laborales')->count())->toBe(1)
        ->and(DB::table('vinculos_laborales')->first()->fecha_ingreso)->toBe($ingreso->fecha_ingreso)
        ->and($detalle->json('data.estado'))->toBe('Aplicada')
        ->and($detalle->json('data.movimiento.aplicacion.por_sistema'))->toBeTrue()
        ->and(collect($detalle->json('data.historial'))->last()['actor'])->toBe(['tipo' => 'sistema', 'id' => null, 'nombre' => 'Sistema'])
        ->and(DB::table('movimientos_personal')->value('asignacion_nueva_id'))->toBe($nueva->id)
        ->and($detalle->json('data.politica.numero'))->toBe(1);
});

it('un movimiento con fecha efectiva alcanzada queda aprobado y el Administrador lo aplica de forma controlada (US5.2)', function () {
    politicaVigente('Ascensos', ['anticipacion_dias' => 0]);
    $admin = actuarComoAdminDeTenant();
    $movimiento = movimientoAprobado($this, ['fecha_efectiva' => hoyDelTenant(0)]);
    $detalle = $this->getJson(URL_MOVIMIENTOS.'/'.$movimiento['id'])->assertOk();
    expect($detalle->json('data.estado'))->toBe('Aprobada')->and($detalle->json('data.acciones.aplicar'))->toBeTrue();
    $clave = (string) Str::uuid();

    $aplicada = accionApi($this, $movimiento['id'], 'aplicacion', ['version' => 2], $clave)->assertOk();
    expect($aplicada->json('data.estado'))->toBe('Aplicada')
        ->and($aplicada->json('data.movimiento.aplicacion.por_sistema'))->toBeFalse()
        ->and(collect($aplicada->json('data.historial'))->last()['actor']['id'])->toBe($admin->id);

    accionApi($this, $movimiento['id'], 'aplicacion', ['version' => 2], $clave)->assertOk();
    accionApi($this, $movimiento['id'], 'aplicacion', ['version' => 3])->assertStatus(409)->assertJsonPath('code', 'APLICACION_NO_PROCEDE');
    expect(DB::table('asignaciones_laborales')->whereNotNull('actuacion_id')->count())->toBe(1)
        ->and(DB::table('actuacion_transiciones')->where('evento', 'Aplicada')->count())->toBe(1);
});

it('lo aplicado deja asignaciones contiguas sin superposición ni vacío y conserva el ingreso original (US5.9)', function () {
    politicaVigente('Ascensos', ['anticipacion_dias' => 0]);
    actuarComoAdminDeTenant();
    $movimiento = movimientoAprobado($this, ['fecha_efectiva' => hoyDelTenant(0)]);
    accionApi($this, $movimiento['id'], 'aplicacion', ['version' => 2])->assertOk();

    $asignaciones = DB::table('asignaciones_laborales')->orderBy('fecha_inicio')->get();
    expect($asignaciones)->toHaveCount(2)
        ->and($asignaciones[0]->fecha_fin)->toBe($asignaciones[1]->fecha_inicio)
        ->and($asignaciones[1]->fecha_fin)->toBeNull()
        ->and(DB::table('vinculos_laborales')->count())->toBe(1)
        ->and(DB::table('vinculos_laborales')->value('fecha_salida'))->toBeNull();
});

it('lo aplicado no se edita ni se elimina y nunca se muestra un movimiento futuro como efectuado (US5.8)', function () {
    politicaVigente('Ascensos', ['anticipacion_dias' => 0]);
    actuarComoAdminDeTenant();
    $aplicado = movimientoAprobado($this, ['fecha_efectiva' => hoyDelTenant(0)]);
    accionApi($this, $aplicado['id'], 'aplicacion', ['version' => 2])->assertOk();
    $futuro = movimientoAprobado($this, ['fecha_efectiva' => hoyDelTenant(20)]);

    $this->patchJson(URL_MOVIMIENTOS.'/'.$aplicado['id'], ['estado' => 'Presentada'])->assertStatus(405);
    $this->deleteJson(URL_MOVIMIENTOS.'/'.$aplicado['id'])->assertStatus(405);
    expect(fn () => DB::table('actuaciones_personal')->where('id', $aplicado['id'])->delete())->toThrow(Exception::class)
        ->and(fn () => DB::table('asignaciones_laborales')->where('actuacion_id', $aplicado['id'])->delete())->toThrow(Exception::class);

    $detalle = $this->getJson(URL_MOVIMIENTOS.'/'.$futuro['id'])->assertOk();
    expect($detalle->json('data.estado'))->toBe('Programada')
        ->and($detalle->json('data.movimiento.aplicacion.estado'))->toBe('Pendiente')
        ->and($detalle->json('data.movimiento.aplicacion.aplicada_en'))->toBeNull();
});
