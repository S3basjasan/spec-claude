<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('el Colaborador retira su solicitud presentada: queda retirada con constancia y no se borra (US2.9)', function () {
    politicaVigente('Permisos');
    ['ficha' => $ficha] = fichaConVinculo();
    $usuario = actuarComoColaboradorDe($ficha);
    $cuerpo = datosNovedad($ficha->id);
    unset($cuerpo['colaborador_id']);
    $id = (int) $this->postJson(URL_MIS_SOLICITUDES.'/novedades', $cuerpo, ['Idempotency-Key' => (string) Str::uuid()])->json('data.id');

    $respuesta = accionApi($this, $id, 'retiro', ['version' => 1], null, URL_MIS_SOLICITUDES)->assertOk();

    expect($respuesta->json('data.estado'))->toBe('Retirada')
        ->and($respuesta->json('data.retirable'))->toBeFalse()
        ->and(DB::table('actuaciones_personal')->count())->toBe(1);

    $transicion = DB::table('actuacion_transiciones')->where('evento', 'Retirada')->first();
    expect($transicion->actor_id)->toBe($usuario->id)->and($transicion->created_at)->not->toBeNull();

    actuarComoAdminDeTenant();
    accionApi($this, $id, 'aprobacion', ['version' => 2, 'justificacion' => 'Tarde'])->assertStatus(409)->assertJsonPath('code', 'ESTADO_NO_RESOLUBLE');
});

it('no admite el retiro de una solicitud ya resuelta (US2.9)', function () {
    politicaVigente('Permisos');
    actuarComoAdminDeTenant();
    ['ficha' => $ficha] = fichaConVinculo();
    $id = (int) presentarNovedadApi($this, datosNovedad($ficha->id))->json('data.id');
    accionApi($this, $id, 'aprobacion', ['version' => 1, 'justificacion' => 'Aprobada'])->assertOk();

    actuarComoColaboradorDe($ficha);
    accionApi($this, $id, 'retiro', ['version' => 2], null, URL_MIS_SOLICITUDES)->assertStatus(409)->assertJsonPath('code', 'ESTADO_NO_RESOLUBLE');
});

it('el Colaborador retira lo que un Administrador registró en su nombre; otro Administrador no puede retirarlo', function () {
    politicaVigente('Permisos');
    $admin = actuarComoAdminDeTenant();
    ['ficha' => $ficha] = fichaConVinculo();
    $propio = (int) presentarNovedadApi($this, datosNovedad($ficha->id))->json('data.id');
    $ajeno = (int) presentarNovedadApi($this, datosNovedad($ficha->id, ['fecha_inicio' => hoyDelTenant(30), 'fecha_fin' => hoyDelTenant(31)]))->json('data.id');

    accionApi($this, $propio, 'retiro', ['version' => 1])->assertOk()->assertJsonPath('data.estado', 'Retirada');

    actuarComoAdminDeTenant();
    accionApi($this, $ajeno, 'retiro', ['version' => 1])->assertStatus(409)->assertJsonPath('code', 'ESTADO_NO_RESOLUBLE');

    actuarComoColaboradorDe($ficha);
    accionApi($this, $ajeno, 'retiro', ['version' => 1], null, URL_MIS_SOLICITUDES)->assertOk()->assertJsonPath('data.estado', 'Retirada');
    expect($admin->id)->toBeInt();
});

it('un retiro y una resolución simultáneos: solo uno prospera y el otro recibe un conflicto (US2.10)', function () {
    politicaVigente('Permisos');
    $admin = actuarComoAdminDeTenant();
    ['ficha' => $ficha] = fichaConVinculo();
    $id = (int) presentarNovedadApi($this, datosNovedad($ficha->id))->json('data.id');

    accionApi($this, $id, 'retiro', ['version' => 1])->assertOk();
    accionApi($this, $id, 'aprobacion', ['version' => 1, 'justificacion' => 'Aprobada'])
        ->assertStatus(409)->assertJsonPath('code', 'SOLICITUD_MODIFICADA');

    expect(DB::table('actuaciones_personal')->value('estado'))->toBe('Retirada')
        ->and(DB::table('actuacion_transiciones')->where('evento', 'Aprobada')->count())->toBe(0)
        ->and($admin->id)->toBeInt();
});

it('solo se retiran permisos, licencias y vacaciones', function () {
    politicaVigente('Ausencias', ['justificacion_obligatoria' => false]);
    actuarComoAdminDeTenant();
    ['ficha' => $ficha] = fichaConVinculo();
    $id = (int) informarAusenciaApi($this, ['colaborador_id' => $ficha->id, 'fecha_inicio' => hoyDelTenant(-1), 'fecha_fin' => hoyDelTenant(-1)])->json('data.id');

    accionApi($this, $id, 'retiro', ['version' => 1])->assertStatus(409)->assertJsonPath('code', 'ESTADO_NO_RESOLUBLE');
});
