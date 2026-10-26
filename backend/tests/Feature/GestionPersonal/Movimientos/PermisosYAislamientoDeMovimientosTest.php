<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('un Colaborador no accede a ninguna ruta del Administrador (US2.5, RMN-CA11)', function () {
    politicaVigente('Permisos');
    ['ficha' => $ficha, 'posicion' => $posicion] = fichaConVinculo();
    $destino = posicionDeMovimiento();
    actuarComoColaboradorDe($ficha);
    $clave = ['Idempotency-Key' => (string) Str::uuid()];

    $this->getJson(URL_MOVIMIENTOS)->assertForbidden();
    $this->getJson(URL_MOVIMIENTOS.'/resumen')->assertForbidden();
    $this->getJson(URL_MOVIMIENTOS.'/1')->assertForbidden();
    $this->postJson(URL_MOVIMIENTOS.'/movimientos', datosMovimiento($ficha->id, $destino->id), $clave)->assertForbidden();
    $this->postJson(URL_MOVIMIENTOS.'/novedades', datosNovedad($ficha->id), $clave)->assertForbidden();
    $this->postJson(URL_MOVIMIENTOS.'/ausencias', datosNovedad($ficha->id), $clave)->assertForbidden();
    foreach (['revision', 'aprobacion', 'rechazo', 'retiro', 'revision-de-ausencia', 'aplicacion'] as $accion) {
        $this->postJson(URL_MOVIMIENTOS."/1/$accion", ['version' => 1], $clave)->assertForbidden();
    }
    expect(DB::table('actuaciones_personal')->count())->toBe(0)->and($posicion->id)->toBeInt();
});

it('sin sesión todas las rutas responden 401', function () {
    $this->getJson(URL_MOVIMIENTOS)->assertUnauthorized();
    $this->getJson(URL_MIS_SOLICITUDES)->assertUnauthorized();
    $this->postJson(URL_MIS_SOLICITUDES.'/novedades', [])->assertUnauthorized();
});

it('una solicitud ajena, de otro tenant o inexistente responde exactamente igual (SC-007)', function () {
    politicaVigente('Permisos');
    politicaVigente('Permisos', [], 2);
    actuarComoAdminDeTenant();
    ['ficha' => $propia] = fichaConVinculo();
    ['ficha' => $otra] = fichaConVinculo();
    $ajena = (int) presentarNovedadApi($this, datosNovedad($otra->id))->json('data.id');

    actuarComoAdminDeTenant(2);
    ['ficha' => $fichaDos] = fichaConVinculo([], 2);
    $deOtroTenant = $this->getJson(URL_MOVIMIENTOS."/$ajena");
    $inexistente = $this->getJson(URL_MOVIMIENTOS.'/99999');
    $deOtroTenant->assertNotFound();
    expect($deOtroTenant->json())->toEqual($inexistente->json());
    accionApi($this, $ajena, 'aprobacion', ['version' => 1, 'justificacion' => 'Intento cruzado'])->assertNotFound()->assertJsonPath('code', 'SOLICITUD_NO_ENCONTRADA');
    expect($this->getJson(URL_MOVIMIENTOS)->json('meta.total'))->toBe(0)
        ->and($this->getJson(URL_MOVIMIENTOS.'/resumen')->json('data.pendientes'))->toBe(0)
        ->and(DB::table('actuaciones_personal')->where('id', $ajena)->value('estado'))->toBe('Presentada');

    presentarNovedadApi($this, datosNovedad($otra->id))->assertNotFound()->assertJsonPath('code', 'FICHA_NO_ENCONTRADA');

    actuarComoColaboradorDe($propia);
    $deOtraPersona = $this->getJson(URL_MIS_SOLICITUDES."/$ajena");
    expect($deOtraPersona->status())->toBe(404)->and($deOtraPersona->json())->toEqual($this->getJson(URL_MIS_SOLICITUDES.'/99999')->json())
        ->and($fichaDos->id)->toBeInt();
});

it('el Colaborador solo lista lo propio y no puede resolver ni retirar lo ajeno', function () {
    politicaVigente('Permisos', ['anticipacion_dias' => 0]);
    actuarComoAdminDeTenant();
    ['ficha' => $propia] = fichaConVinculo();
    ['ficha' => $otra] = fichaConVinculo();
    $mia = (int) presentarNovedadApi($this, datosNovedad($propia->id))->json('data.id');
    $ajena = (int) presentarNovedadApi($this, datosNovedad($otra->id))->json('data.id');

    actuarComoColaboradorDe($propia);
    $lista = $this->getJson(URL_MIS_SOLICITUDES)->assertOk();
    expect(collect($lista->json('data'))->pluck('id')->all())->toBe([$mia]);

    accionApi($this, $ajena, 'retiro', ['version' => 1], null, URL_MIS_SOLICITUDES)->assertNotFound();
    $this->getJson(URL_MIS_SOLICITUDES.'?colaborador_id='.$otra->id)->assertStatus(200);
    expect(collect($this->getJson(URL_MIS_SOLICITUDES.'?colaborador_id='.$otra->id)->json('data'))->pluck('id')->all())->toBe([$mia])
        ->and(DB::table('actuaciones_personal')->where('id', $ajena)->value('estado'))->toBe('Presentada');
});

it('la forma comunicable del Colaborador no contiene campos restringidos (FR-037)', function () {
    politicaVigente('Permisos');
    actuarComoAdminDeTenant();
    ['ficha' => $ficha] = fichaConVinculo();
    $id = (int) presentarNovedadApi($this, datosNovedad($ficha->id))->json('data.id');
    actuarComoColaboradorDe($ficha);

    $detalle = $this->getJson(URL_MIS_SOLICITUDES."/$id")->assertOk()->json('data');

    expect(array_keys($detalle))->not->toContain('historial', 'comprobaciones', 'cruces', 'presentada', 'documentos', 'acciones')
        ->and(array_keys($detalle['politica']))->toBe(['numero', 'requisitos']);
});

it('la aceptación de un respaldo respeta los permisos documentales del Colaborador', function () {
    politicaVigente('Permisos', ['documento_obligatorio' => true]);
    ['ficha' => $ficha] = fichaConVinculo();
    $visible = respaldoDe($ficha, ['visible_colaborador' => true]);
    $oculto = respaldoDe($ficha, ['visible_colaborador' => false]);
    actuarComoColaboradorDe($ficha);
    $cuerpo = datosNovedad($ficha->id);
    unset($cuerpo['colaborador_id']);
    $encabezado = fn () => ['Idempotency-Key' => (string) Str::uuid()];

    expect(collect($this->getJson(URL_MIS_SOLICITUDES.'/documentos')->json('data'))->pluck('id')->all())->toBe([$visible->id]);
    $this->postJson(URL_MIS_SOLICITUDES.'/novedades', [...$cuerpo, 'documento_ids' => [$oculto->id]], $encabezado())->assertStatus(422)->assertJsonPath('code', 'VALIDACION');
    $this->postJson(URL_MIS_SOLICITUDES.'/novedades', [...$cuerpo, 'documento_ids' => []], $encabezado())->assertStatus(422)->assertJsonPath('code', 'DOCUMENTO_REQUERIDO');
    $this->postJson(URL_MIS_SOLICITUDES.'/novedades', [...$cuerpo, 'documento_ids' => [$visible->id]], $encabezado())->assertCreated();

    // Sin permiso documental no hay respaldos referenciables.
    $sin = fichaConVinculo()['ficha'];
    actuarComoColaboradorDe($sin, ['puede_consultar_ficha' => true, 'puede_consultar_documentos' => false]);
    expect($this->getJson(URL_MIS_SOLICITUDES.'/documentos')->json('data'))->toBe([]);
});
