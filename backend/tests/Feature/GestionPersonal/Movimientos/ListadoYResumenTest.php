<?php

use Illuminate\Support\Facades\DB;

it('lista las solicitudes del tenant con tipo, colaborador, presentación, responsable, periodo y estado (US2.1)', function () {
    politicaVigente('Permisos');
    politicaVigente('Vacaciones', ['anticipacion_dias' => 0]);
    $admin = actuarComoAdminDeTenant();
    ['ficha' => $a] = fichaConVinculo(['nombres' => 'Ana', 'apellidos' => 'Núñez']);
    ['ficha' => $b] = fichaConVinculo(['nombres' => 'Luis', 'apellidos' => 'Paredes']);
    presentarNovedadApi($this, datosNovedad($a->id))->assertCreated();
    presentarNovedadApi($this, datosNovedad($b->id, ['tipo' => 'Vacaciones', 'fecha_inicio' => hoyDelTenant(20), 'fecha_fin' => hoyDelTenant(25)]))->assertCreated();

    $respuesta = $this->getJson(URL_MOVIMIENTOS)->assertOk();
    $fila = collect($respuesta->json('data'))->firstWhere('tipo', 'Vacaciones');

    expect($respuesta->json('meta.total'))->toBe(2)
        ->and($fila['colaborador']['nombre'])->toBe('Luis Paredes')
        ->and($fila['presentada_por']['id'])->toBe($admin->id)
        ->and($fila['periodo'])->toBe(['inicio' => hoyDelTenant(20), 'fin' => hoyDelTenant(25)])
        ->and($fila['estado'])->toBe('Presentada')
        ->and($fila['fecha_presentacion'])->toBe(hoyDelTenant());
});

it('filtra por tipo, estado, colaborador y periodo, y pagina', function () {
    politicaVigente('Permisos', ['anticipacion_dias' => 0]);
    politicaVigente('Licencias', ['anticipacion_dias' => 0]);
    actuarComoAdminDeTenant();
    ['ficha' => $a] = fichaConVinculo();
    ['ficha' => $b] = fichaConVinculo();
    $primero = (int) presentarNovedadApi($this, datosNovedad($a->id, ['fecha_inicio' => hoyDelTenant(1), 'fecha_fin' => hoyDelTenant(2)]))->json('data.id');
    presentarNovedadApi($this, datosNovedad($a->id, ['tipo' => 'Licencia', 'fecha_inicio' => hoyDelTenant(40), 'fecha_fin' => hoyDelTenant(45)]))->assertCreated();
    presentarNovedadApi($this, datosNovedad($b->id, ['fecha_inicio' => hoyDelTenant(1), 'fecha_fin' => hoyDelTenant(1)]))->assertCreated();
    accionApi($this, $primero, 'revision', ['version' => 1])->assertOk();

    expect($this->getJson(URL_MOVIMIENTOS.'?tipo=Licencia')->json('meta.total'))->toBe(1)
        ->and($this->getJson(URL_MOVIMIENTOS.'?estado=EnRevision')->json('meta.total'))->toBe(1)
        ->and($this->getJson(URL_MOVIMIENTOS.'?colaborador_id='.$b->id)->json('meta.total'))->toBe(1)
        ->and($this->getJson(URL_MOVIMIENTOS.'?desde='.hoyDelTenant(40).'&hasta='.hoyDelTenant(41))->json('meta.total'))->toBe(1)
        ->and($this->getJson(URL_MOVIMIENTOS.'?desde='.hoyDelTenant(100))->json('meta.total'))->toBe(0)
        ->and($this->getJson(URL_MOVIMIENTOS.'?grupo=pendientes')->json('meta.total'))->toBe(3)
        ->and($this->getJson(URL_MOVIMIENTOS.'?grupo=resueltas')->json('meta.total'))->toBe(0)
        ->and($this->getJson(URL_MOVIMIENTOS.'?per_page=2&page=2')->json('data'))->toHaveCount(1);

    $this->getJson(URL_MOVIMIENTOS.'?tipo=Inventado')->assertStatus(422);
    $this->getJson(URL_MOVIMIENTOS.'?per_page=500')->assertStatus(422);
});

it('un listado vacío responde con éxito y sin registros, distinto de un error', function () {
    actuarComoAdminDeTenant();

    $this->getJson(URL_MOVIMIENTOS)->assertOk()->assertJsonPath('data', [])->assertJsonPath('meta.total', 0);
});

it('el resumen cuenta pendientes, en revisión, programados, aplicados e incidencias', function () {
    politicaVigente('Permisos', ['anticipacion_dias' => 0]);
    actuarComoAdminDeTenant();
    ['ficha' => $ficha] = fichaConVinculo();
    $uno = (int) presentarNovedadApi($this, datosNovedad($ficha->id))->json('data.id');
    presentarNovedadApi($this, datosNovedad($ficha->id, ['fecha_inicio' => hoyDelTenant(50), 'fecha_fin' => hoyDelTenant(51)]))->assertCreated();
    accionApi($this, $uno, 'revision', ['version' => 1])->assertOk();

    $this->getJson(URL_MOVIMIENTOS.'/resumen')->assertOk()->assertExactJson(['data' => [
        'pendientes' => 1, 'en_revision' => 1, 'programados' => 0, 'aplicados' => 0, 'incidencias' => 0,
    ]]);

    // Una novedad aprobada no cuenta como movimiento programado.
    accionApi($this, $uno, 'aprobacion', ['version' => 2, 'justificacion' => 'Aprobada', 'reconoce_cruce' => true])->assertOk();
    expect($this->getJson(URL_MOVIMIENTOS.'/resumen')->json('data.programados'))->toBe(0)
        ->and(DB::table('actuaciones_personal')->where('estado', 'Aprobada')->count())->toBe(1);
});
