<?php

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\PosicionModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\PuestoModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('presenta un ascenso trazable sin modificar ninguna asignación (US4.1, RMN-CA01)', function () {
    politicaVigente('Ascensos', ['anticipacion_dias' => 5]);
    $admin = actuarComoAdminDeTenant();
    ['ficha' => $ficha, 'posicion' => $origen] = fichaConVinculo();
    $destino = posicionDeMovimiento();
    $antes = [DB::table('vinculos_laborales')->get()->toArray(), DB::table('asignaciones_laborales')->get()->toArray()];

    $respuesta = proponerMovimientoApi($this, datosMovimiento($ficha->id, $destino->id))->assertCreated();

    expect($respuesta->json('data.estado'))->toBe('Presentada')
        ->and($respuesta->json('data.categoria'))->toBe('Movimiento')
        ->and($respuesta->json('data.movimiento.fecha_efectiva'))->toBe(hoyDelTenant(30))
        ->and($respuesta->json('data.movimiento.posicion_actual.id'))->toBe($origen->id)
        ->and($respuesta->json('data.movimiento.posicion_actual.puesto'))->toBeString()
        ->and($respuesta->json('data.movimiento.posicion_destino.id'))->toBe($destino->id)
        ->and($respuesta->json('data.movimiento.aplicacion.estado'))->toBe('Pendiente')
        ->and($respuesta->json('data.politica.numero'))->toBe(1)
        ->and($respuesta->json('data.presentada.por.id'))->toBe($admin->id)
        ->and([DB::table('vinculos_laborales')->get()->toArray(), DB::table('asignaciones_laborales')->get()->toArray()])->toEqual($antes);
});

it('rechaza un destino inexistente, suprimido, de otro tenant, con puesto inactivo o igual al actual (US4.7)', function () {
    politicaVigente('Ascensos', ['anticipacion_dias' => 0]);
    tenantDos();
    actuarComoAdminDeTenant();
    ['ficha' => $ficha, 'posicion' => $origen] = fichaConVinculo();
    $suprimida = posicionDeMovimiento();
    PosicionModel::query()->whereKey($suprimida->id)->update(['estado' => 'Suprimida']);
    $inactiva = posicionDeMovimiento();
    PuestoModel::query()->whereKey($inactiva->puesto_id)->update(['estado' => 'Inactivo']);
    $ajena = posicionDeMovimiento([], 2);

    foreach ([99999, $suprimida->id, $inactiva->id, $ajena->id, $origen->id] as $destinoId) {
        proponerMovimientoApi($this, datosMovimiento($ficha->id, $destinoId, ['fecha_efectiva' => hoyDelTenant(3)]))
            ->assertStatus(422)->assertJsonPath('code', 'POSICION_NO_DISPONIBLE');
    }
    expect(DB::table('actuaciones_personal')->count())->toBe(0);
});

it('aplica la anticipación sobre la fecha efectiva y exige justificación siempre (US4.6, FR-013)', function () {
    politicaVigente('Ascensos', ['anticipacion_dias' => 5]);
    actuarComoAdminDeTenant();
    ['ficha' => $ficha] = fichaConVinculo();
    $destino = posicionDeMovimiento();

    proponerMovimientoApi($this, datosMovimiento($ficha->id, $destino->id, ['fecha_efectiva' => hoyDelTenant(2)]))
        ->assertStatus(422)->assertJsonPath('code', 'ANTICIPACION_INSUFICIENTE');
    proponerMovimientoApi($this, datosMovimiento($ficha->id, $destino->id, ['fecha_efectiva' => hoyDelTenant(-1)]))
        ->assertStatus(422)->assertJsonPath('code', 'FECHA_PASADA');
    proponerMovimientoApi($this, datosMovimiento($ficha->id, $destino->id, ['motivo' => '  ']))
        ->assertStatus(422)->assertJsonPath('code', 'VALIDACION')->assertJsonValidationErrorFor('motivo');
    proponerMovimientoApi($this, datosMovimiento($ficha->id, $destino->id, ['fecha_efectiva' => 'mañana']))
        ->assertStatus(422)->assertJsonPath('code', 'VALIDACION');
    expect(DB::table('actuaciones_personal')->count())->toBe(0);

    proponerMovimientoApi($this, datosMovimiento($ficha->id, $destino->id, ['fecha_efectiva' => hoyDelTenant(5)]))->assertCreated();
});

it('exige el documento adicional cuando la política lo configura', function () {
    politicaVigente('Traslados', ['anticipacion_dias' => 0, 'documento_adicional_obligatorio' => true]);
    actuarComoAdminDeTenant();
    ['ficha' => $ficha] = fichaConVinculo();
    $destino = posicionDeMovimiento();
    $cuerpo = datosMovimiento($ficha->id, $destino->id, ['tipo' => 'Traslado']);

    proponerMovimientoApi($this, $cuerpo)->assertStatus(422)->assertJsonPath('code', 'DOCUMENTO_REQUERIDO');
    proponerMovimientoApi($this, [...$cuerpo, 'documento_ids' => [respaldoDe($ficha)->id]])->assertCreated()->assertJsonPath('data.tipo', 'Traslado');
});

it('distingue política sin configurar y sin solicitudes nuevas también en movimientos', function () {
    actuarComoAdminDeTenant();
    ['ficha' => $ficha] = fichaConVinculo();
    $destino = posicionDeMovimiento();

    proponerMovimientoApi($this, datosMovimiento($ficha->id, $destino->id))->assertStatus(422)->assertJsonPath('code', 'POLITICA_SIN_CONFIGURAR');
    politicaVigente('Ascensos', ['disponible' => false]);
    proponerMovimientoApi($this, datosMovimiento($ficha->id, $destino->id))->assertStatus(422)->assertJsonPath('code', 'POLITICA_NO_ADMITE_SOLICITUDES');
});

it('exige un vínculo vigente con asignación y una fecha efectiva posterior a su inicio', function () {
    politicaVigente('Ascensos', ['anticipacion_dias' => 0]);
    actuarComoAdminDeTenant();
    $destino = posicionDeMovimiento();
    $sinVinculo = fichaDe(['numero_identificacion' => 'SV2']);
    ['ficha' => $reciente] = fichaConVinculo([], 1, hoyDelTenant(0));

    proponerMovimientoApi($this, datosMovimiento($sinVinculo->id, $destino->id, ['fecha_efectiva' => hoyDelTenant(3)]))
        ->assertStatus(422)->assertJsonPath('code', 'SIN_VINCULO_VIGENTE');
    proponerMovimientoApi($this, datosMovimiento($reciente->id, $destino->id, ['fecha_efectiva' => hoyDelTenant(0)]))
        ->assertStatus(422)->assertJsonPath('code', 'VALIDACION')->assertJsonValidationErrorFor('fecha_efectiva');
});

it('solo acepta Ascenso o Traslado como tipo y no admite campos fijados por el cliente', function () {
    politicaVigente('Ascensos', ['anticipacion_dias' => 0]);
    actuarComoAdminDeTenant();
    ['ficha' => $ficha] = fichaConVinculo();
    $destino = posicionDeMovimiento();

    proponerMovimientoApi($this, datosMovimiento($ficha->id, $destino->id, ['tipo' => 'Permiso']))->assertStatus(422)->assertJsonPath('code', 'VALIDACION');
    proponerMovimientoApi($this, [...datosMovimiento($ficha->id, $destino->id), 'evaluacion_apto' => true])->assertStatus(422)->assertJsonPath('code', 'CAMPO_NO_PERMITIDO');
    proponerMovimientoApi($this, [...datosMovimiento($ficha->id, $destino->id), 'posicion_origen_id' => 1])->assertStatus(422)->assertJsonPath('code', 'CAMPO_NO_PERMITIDO');
});

it('la misma propuesta confirmada dos veces no se duplica', function () {
    politicaVigente('Ascensos', ['anticipacion_dias' => 0]);
    actuarComoAdminDeTenant();
    ['ficha' => $ficha] = fichaConVinculo();
    $destino = posicionDeMovimiento();
    $clave = (string) Str::uuid();

    proponerMovimientoApi($this, datosMovimiento($ficha->id, $destino->id), $clave)->assertCreated();
    proponerMovimientoApi($this, datosMovimiento($ficha->id, $destino->id), $clave)->assertOk();

    expect(DB::table('actuaciones_personal')->count())->toBe(1)->and(DB::table('movimientos_personal')->count())->toBe(1);
});

it('el Colaborador no ve propuestas ni movimientos rechazados, y un Colaborador no propone (US4.8, US4.9)', function () {
    politicaVigente('Ascensos', ['anticipacion_dias' => 0]);
    actuarComoAdminDeTenant();
    $movimiento = movimientoPresentado($this);
    $rechazado = movimientoPresentado($this);
    accionApi($this, $rechazado['id'], 'rechazo', ['version' => 1, 'justificacion' => 'No cumple el perfil'])->assertOk();

    foreach ([$movimiento, $rechazado] as $caso) {
        actuarComoColaboradorDe($caso['ficha']);
        expect($this->getJson(URL_MIS_SOLICITUDES)->json('data'))->toBe([]);
        $this->getJson(URL_MIS_SOLICITUDES.'/'.$caso['id'])->assertNotFound();
        $this->postJson(URL_MOVIMIENTOS.'/movimientos', datosMovimiento($caso['ficha']->id, $caso['destino']->id), ['Idempotency-Key' => (string) Str::uuid()])->assertForbidden();
    }
});

it('ofrece el contexto de movimiento: situación actual, destinos de Estructura y estado de las políticas', function () {
    politicaVigente('Ascensos', ['anticipacion_dias' => 3]);
    actuarComoAdminDeTenant();
    ['ficha' => $ficha, 'posicion' => $origen] = fichaConVinculo();
    $destino = posicionDeMovimiento();

    $contexto = $this->getJson(URL_MOVIMIENTOS.'/contexto-de-movimiento?colaborador_id='.$ficha->id)->assertOk()->json('data');

    expect($contexto['vinculo_vigente'])->toBeTrue()
        ->and($contexto['posicion_actual']['id'])->toBe($origen->id)
        ->and(collect($contexto['destinos']['items'])->pluck('id')->all())->toContain($destino->id)->not->toContain($origen->id)
        ->and($contexto['politicas']['ascensos']['situacion'])->toBe('Vigente')
        ->and($contexto['politicas']['ascensos']['parametros']['anticipacion_dias'])->toBe(3)
        ->and($contexto['politicas']['traslados']['situacion'])->toBe('SinConfigurar');

    $this->getJson(URL_MOVIMIENTOS.'/contexto-de-movimiento')->assertStatus(422);
    tenantDos();
    ['ficha' => $otra] = fichaConVinculo([], 2);
    $this->getJson(URL_MOVIMIENTOS.'/contexto-de-movimiento?colaborador_id='.$otra->id)->assertNotFound()->assertJsonPath('code', 'FICHA_NO_ENCONTRADA');
});
