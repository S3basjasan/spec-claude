<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('formaliza la salida: crea el expediente, copia la plantilla, conserva la política y no toca el vínculo (CA-01, CA-02)', function () {
    $politica = politicaVigente('Offboarding');
    actuarComoAdminDeTenant();
    ['ficha' => $ficha, 'vinculo' => $vinculo] = fichaConVinculo();
    $antes = [DB::table('vinculos_laborales')->get()->toArray(), DB::table('asignaciones_laborales')->get()->toArray()];

    $respuesta = formalizarSalidaApi($this, datosSalida($ficha->id))->assertCreated();

    expect($respuesta->json('data.estado'))->toBe('Programada')
        ->and($respuesta->json('data.motivo.clave'))->toBe('Renuncia')
        ->and($respuesta->json('data.fecha_efectiva'))->toBe(hoyDelTenant(10))
        ->and($respuesta->json('data.politica.version_id'))->toBe($politica->id)
        ->and($respuesta->json('data.cuenta_estado'))->toBe('Pendiente')
        ->and($respuesta->json('data.cierre.estado'))->toBe('Pendiente')
        ->and(collect($respuesta->json('data.tareas'))->pluck('nombre')->all())->toBe(['Devolver equipos', 'Cerrar accesos', 'Entrevista de salida'])
        ->and(collect($respuesta->json('data.tareas'))->pluck('tipo')->all())->toBe(['Plantilla', 'Plantilla', 'Entrevista'])
        ->and($respuesta->json('data.entrevista.estado'))->toBe('Pendiente')
        ->and([DB::table('vinculos_laborales')->get()->toArray(), DB::table('asignaciones_laborales')->get()->toArray()])->toEqual($antes)
        ->and(DB::table('offboarding_eventos')->where('expediente_id', $respuesta->json('data.id'))->pluck('evento')->all())->toBe(['Formalizada']);
});

it('las tareas ya copiadas no cambian cuando se publica otra versión de la política (CA-02)', function () {
    politicaVigente('Offboarding');
    $salida = salidaFormalizada($this);

    politicaPublicada('Offboarding', ['parametros' => ['tareas' => [['nombre' => 'Otra tarea', 'descripcion' => null, 'exige_evidencia' => false]]], 'inicio' => new DateTimeImmutable('-1 hour', new DateTimeZone('UTC'))]);

    $detalle = $this->getJson(URL_OFFBOARDING.'/'.$salida['id'])->assertOk();
    expect(collect($detalle->json('data.tareas'))->pluck('nombre')->all())->toBe(['Devolver equipos', 'Cerrar accesos', 'Entrevista de salida'])
        ->and($detalle->json('data.politica.numero'))->toBe(1);
});

it('rechaza sin política publicada o con política no disponible sin modificar la situación laboral (CA-03)', function () {
    actuarComoAdminDeTenant();
    ['ficha' => $ficha] = fichaConVinculo();

    formalizarSalidaApi($this, datosSalida($ficha->id))->assertStatus(422)->assertJsonPath('code', 'POLITICA_SIN_CONFIGURAR');

    expect(DB::table('offboarding_expedientes')->count())->toBe(0)
        ->and(DB::table('vinculos_laborales')->whereNotNull('fecha_salida')->count())->toBe(0);
});

it('valida motivo, fecha efectiva y campos no permitidos (CA-03)', function () {
    politicaVigente('Offboarding');
    actuarComoAdminDeTenant();
    ['ficha' => $ficha] = fichaConVinculo();

    formalizarSalidaApi($this, datosSalida($ficha->id, ['motivo' => 'Despido']))->assertStatus(422)->assertJsonPath('code', 'VALIDACION')->assertJsonValidationErrors(['motivo']);
    formalizarSalidaApi($this, datosSalida($ficha->id, ['motivo' => null]))->assertStatus(422)->assertJsonPath('code', 'VALIDACION');
    formalizarSalidaApi($this, datosSalida($ficha->id, ['fecha_efectiva' => '2026-13-45']))->assertStatus(422)->assertJsonPath('code', 'VALIDACION');
    formalizarSalidaApi($this, datosSalida($ficha->id, ['fecha_efectiva' => hoyDelTenant(-1)]))->assertStatus(422)->assertJsonPath('code', 'FECHA_EFECTIVA_INVALIDA');
    formalizarSalidaApi($this, datosSalida($ficha->id, ['tenant_id' => 2, 'estado' => 'Efectiva']))->assertStatus(422)->assertJsonPath('code', 'CAMPO_NO_PERMITIDO');
    $this->postJson(URL_OFFBOARDING, datosSalida($ficha->id))->assertStatus(422)->assertJsonPath('code', 'CLAVE_DE_OPERACION_REQUERIDA');

    expect(DB::table('offboarding_expedientes')->count())->toBe(0);
});

it('la fecha efectiva debe ser posterior al inicio del vínculo y hoy es válido', function () {
    politicaVigente('Offboarding');
    actuarComoAdminDeTenant();
    ['ficha' => $ficha] = fichaConVinculo([], 1, hoyDelTenant());

    formalizarSalidaApi($this, datosSalida($ficha->id, ['fecha_efectiva' => hoyDelTenant()]))->assertStatus(422)->assertJsonPath('code', 'FECHA_EFECTIVA_INVALIDA');
    formalizarSalidaApi($this, datosSalida($ficha->id, ['fecha_efectiva' => hoyDelTenant(1)]))->assertCreated();

    ['ficha' => $otra] = fichaConVinculo();
    formalizarSalidaApi($this, datosSalida($otra->id, ['fecha_efectiva' => hoyDelTenant()]))->assertCreated();
});

it('rechaza una ficha sin vínculo vigente y una ficha inexistente o de otro tenant (CA-03)', function () {
    politicaVigente('Offboarding');
    actuarComoAdminDeTenant();
    $sinVinculo = fichaDe();
    $deOtro = fichaConVinculo([], 2)['ficha'];

    formalizarSalidaApi($this, datosSalida($sinVinculo->id))->assertStatus(422)->assertJsonPath('code', 'SIN_VINCULO_VIGENTE');
    formalizarSalidaApi($this, datosSalida($deOtro->id))->assertStatus(404)->assertJsonPath('code', 'FICHA_NO_ENCONTRADA');
    formalizarSalidaApi($this, datosSalida(999999))->assertStatus(404)->assertJsonPath('code', 'FICHA_NO_ENCONTRADA');
});

it('un vínculo ya terminado no admite una salida', function () {
    politicaVigente('Offboarding');
    actuarComoAdminDeTenant();
    $ficha = fichaDe();
    vinculoDe($ficha->id, hoyDelTenant(-300), hoyDelTenant(-5), posicionDeMovimiento()->id);

    formalizarSalidaApi($this, datosSalida($ficha->id))->assertStatus(422)->assertJsonPath('code', 'SIN_VINCULO_VIGENTE');
});

it('solo hay una salida viva por vínculo y tras cancelarla puede formalizarse otra (RN única)', function () {
    politicaVigente('Offboarding');
    $salida = salidaFormalizada($this);

    formalizarSalidaApi($this, datosSalida($salida['ficha']->id))->assertStatus(409)->assertJsonPath('code', 'SALIDA_YA_FORMALIZADA');
    expect(DB::table('offboarding_expedientes')->count())->toBe(1);

    accionSalidaApi($this, $salida['id'], 'cancelacion', ['version' => 1, 'razon' => 'Se retiró la renuncia'])->assertOk()->assertJsonPath('data.estado', 'Cancelada');
    formalizarSalidaApi($this, datosSalida($salida['ficha']->id, ['motivo' => 'Jubilacion']))->assertCreated();

    expect(DB::table('offboarding_expedientes')->count())->toBe(2);
});

it('es idempotente: la misma clave y contenido devuelve el resultado anterior y otra clave reutilizada es conflicto (CA-01)', function () {
    politicaVigente('Offboarding');
    actuarComoAdminDeTenant();
    ['ficha' => $ficha] = fichaConVinculo();
    ['ficha' => $otra] = fichaConVinculo();
    $clave = (string) Str::uuid();

    $primera = formalizarSalidaApi($this, datosSalida($ficha->id), $clave)->assertCreated();
    $repetida = formalizarSalidaApi($this, datosSalida($ficha->id), $clave)->assertOk();

    expect($repetida->json('data.id'))->toBe($primera->json('data.id'))
        ->and(DB::table('offboarding_expedientes')->count())->toBe(1)
        ->and(DB::table('offboarding_eventos')->count())->toBe(1);

    formalizarSalidaApi($this, datosSalida($otra->id), $clave)->assertStatus(409)->assertJsonPath('code', 'CLAVE_DE_OPERACION_REUTILIZADA');
    formalizarSalidaApi($this, datosSalida($ficha->id, ['motivo' => 'Terminacion']), $clave)->assertStatus(409)->assertJsonPath('code', 'CLAVE_DE_OPERACION_REUTILIZADA');
});

it('guarda los respaldos accesibles y rechaza los inexistentes o de otra ficha', function () {
    politicaVigente('Offboarding');
    actuarComoAdminDeTenant();
    ['ficha' => $ficha] = fichaConVinculo();
    $ajeno = respaldoDe(fichaDe(['numero_identificacion' => 'X'.uniqid()]));
    $propio = respaldoDe($ficha);

    formalizarSalidaApi($this, datosSalida($ficha->id, ['documento_ids' => [$ajeno->id]]))->assertStatus(422)->assertJsonPath('code', 'VALIDACION');
    formalizarSalidaApi($this, datosSalida($ficha->id, ['documento_ids' => [999999]]))->assertStatus(422);

    $ok = formalizarSalidaApi($this, datosSalida($ficha->id, ['documento_ids' => [$propio->id]]))->assertCreated();
    expect($ok->json('data.respaldos'))->toBe([['id' => $propio->id, 'disponible' => true]])
        ->and(json_encode($ok->json()))->not->toContain($propio->titulo);
});

it('el contexto informa vínculo, motivos, política y salida existente sin reservar versión', function () {
    actuarComoAdminDeTenant();
    ['ficha' => $ficha] = fichaConVinculo();

    $sin = $this->getJson(URL_OFFBOARDING.'/contexto?colaborador_id='.$ficha->id)->assertOk();
    expect($sin->json('data.politica.situacion'))->not->toBe('Configurada')
        ->and($sin->json('data.vinculo_vigente.fecha_ingreso'))->toBe(hoyDelTenant(-200))
        ->and(collect($sin->json('data.motivos'))->pluck('clave')->all())->toBe(['Renuncia', 'Terminacion', 'FinContrato', 'Jubilacion'])
        ->and($sin->json('data.salida_existente'))->toBeNull();

    politicaVigente('Offboarding');
    $con = $this->getJson(URL_OFFBOARDING.'/contexto?colaborador_id='.$ficha->id)->assertOk();
    expect(collect($con->json('data.politica.tareas'))->pluck('nombre')->all())->toBe(['Devolver equipos', 'Cerrar accesos']);

    formalizarSalidaApi($this, datosSalida($ficha->id))->assertCreated();
    expect($this->getJson(URL_OFFBOARDING.'/contexto?colaborador_id='.$ficha->id)->json('data.salida_existente.estado'))->toBe('Programada');

    $this->getJson(URL_OFFBOARDING.'/contexto')->assertStatus(422);
    $this->getJson(URL_OFFBOARDING.'/contexto?colaborador_id=999999')->assertStatus(404);
});

it('cancelar exige razón y versión, deja la salida sin efecto y una salida efectiva no se cancela', function () {
    politicaVigente('Offboarding');
    $salida = salidaFormalizada($this);

    accionSalidaApi($this, $salida['id'], 'cancelacion', ['version' => 1])->assertStatus(422)->assertJsonPath('code', 'VALIDACION');
    accionSalidaApi($this, $salida['id'], 'cancelacion', ['version' => 9, 'razon' => 'x'])->assertStatus(409)->assertJsonPath('code', 'EXPEDIENTE_MODIFICADO');
    $cancelada = accionSalidaApi($this, $salida['id'], 'cancelacion', ['version' => 1, 'razon' => 'Se retiró la renuncia'])->assertOk();

    expect($cancelada->json('data.estado'))->toBe('Cancelada')
        ->and($cancelada->json('data.cancelacion.razon'))->toBe('Se retiró la renuncia')
        ->and($cancelada->json('data.acciones.cancelar'))->toBeFalse();

    accionSalidaApi($this, $salida['id'], 'cancelacion', ['version' => 2, 'razon' => 'otra'])->assertStatus(409)->assertJsonPath('code', 'ESTADO_NO_PERMITIDO');
});
