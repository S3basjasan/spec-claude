<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

afterEach(fn () => Carbon::setTestNow());

function inicioLocalManana(): string
{
    return now('America/Guayaquil')->addDay()->format('Y-m-d\TH:i');
}

it('la publicación inmediata deja la versión 1 vigente con motivo, revisión y responsables (PO-CA03)', function () {
    $admin = actuarComoAdminDeTenant();
    $borrador = borradorDe('Permisos');

    $respuesta = publicarApi($this, 'Permisos', $borrador->id)->assertCreated();

    expect($respuesta->json('data.numero'))->toBe(1)->and($respuesta->json('data.estado'))->toBe('Vigente')
        ->and($respuesta->json('data.motivo_cambio'))->toBe('Revisión inicial de condiciones')
        ->and($respuesta->json('data.revision.revisor'))->toBe('Ana Núñez')
        ->and($respuesta->json('data.revision.observaciones'))->toBe('Revisado con Talento Humano')
        ->and($respuesta->json('data.publicada_por'))->toBe($admin->id)
        ->and($respuesta->json('data.inicio_vigencia'))->not->toBeNull()
        ->and(DB::table('politica_versiones')->where('id', $borrador->id)->value('condiciones_hash'))->toHaveLength(64);

    $detalle = $this->getJson(urlPoliticas('permisos'))->assertOk();
    expect($detalle->json('data.resumen.situacion'))->toBe('Vigente')->and($detalle->json('data.vigente.numero'))->toBe(1)
        ->and($detalle->json('data.borrador'))->toBeNull();
});

it('las condiciones selladas incluyen tipo, número, parámetros y reglas fijas pero no datos internos', function () {
    actuarComoAdminDeTenant();
    $borrador = borradorDe('Ascensos');
    publicarApi($this, 'Ascensos', $borrador->id)->assertCreated();

    $condiciones = json_decode((string) DB::table('politica_versiones')->where('id', $borrador->id)->value('condiciones'), true);

    expect($condiciones['tipo'])->toBe('Ascensos')->and($condiciones['numero'])->toBe(1)->and($condiciones['esquema'])->toBe(1)
        ->and($condiciones['parametros']['anticipacion_dias'])->toBe(5)
        ->and(array_column($condiciones['reglas_fijas'], 'clave'))->toContain('MOV-EVALUACION', 'MOV-JUSTIFICACION')
        ->and(array_keys($condiciones))->not->toContain('motivo_cambio', 'revision', 'descripcion');
});

it('programar un inicio futuro deja la versión Programada (PO-CA04)', function () {
    actuarComoAdminDeTenant();
    $borrador = borradorDe('Permisos');

    $respuesta = publicarApi($this, 'Permisos', $borrador->id, ['inicio' => ['modo' => 'programado', 'fecha_hora_local' => inicioLocalManana()]])->assertCreated();

    expect($respuesta->json('data.estado'))->toBe('Programada')->and($respuesta->json('data.inicio_vigencia_local'))->toBe(inicioLocalManana());
});

it('rechaza publicar sin motivo, sin revisor o con textos demasiado largos (PO-CA05, PO-CA07)', function () {
    actuarComoAdminDeTenant();
    $borrador = borradorDe('Permisos');

    $sinNada = publicarApi($this, 'Permisos', $borrador->id, ['motivo' => '', 'revision' => ['revisor' => '']])->assertUnprocessable();
    expect($sinNada->json('code'))->toBe('VALIDACION')->and($sinNada->json('errors'))->toHaveKeys(['motivo', 'revision.revisor']);

    $largos = publicarApi($this, 'Permisos', $borrador->id, ['motivo' => str_repeat('a', 501), 'revision' => ['revisor' => str_repeat('b', 151), 'observaciones' => str_repeat('c', 1001)]])->assertUnprocessable();
    expect($largos->json('errors'))->toHaveKeys(['motivo', 'revision.revisor', 'revision.observaciones'])
        ->and(DB::table('politica_versiones')->where('id', $borrador->id)->value('numero'))->toBeNull();
});

it('rechaza publicar un borrador incompleto señalando cada campo (PO-CA05)', function () {
    actuarComoAdminDeTenant();
    $borrador = borradorDe('Permisos', ['parametros' => ['disponible' => true], 'descripcion' => '']);

    $respuesta = publicarApi($this, 'Permisos', $borrador->id)->assertUnprocessable();

    expect($respuesta->json('errors'))->toHaveKeys(['descripcion', 'parametros.justificacion_obligatoria', 'parametros.documento_obligatorio', 'parametros.anticipacion_dias'])
        ->and(DB::table('politicas_personal')->where('tipo', 'Permisos')->value('ultimo_numero'))->toBe(0);
});

it('rechaza un inicio en el pasado y formatos de inicio inválidos (PO-CA06)', function () {
    actuarComoAdminDeTenant();
    $borrador = borradorDe('Permisos');
    $ayer = now('America/Guayaquil')->subDay()->format('Y-m-d\TH:i');

    $pasado = publicarApi($this, 'Permisos', $borrador->id, ['inicio' => ['modo' => 'programado', 'fecha_hora_local' => $ayer]])->assertUnprocessable();
    expect($pasado->json('code'))->toBe('INICIO_EN_EL_PASADO');

    publicarApi($this, 'Permisos', $borrador->id, ['inicio' => ['modo' => 'programado', 'fecha_hora_local' => 'mañana']])->assertUnprocessable();
    publicarApi($this, 'Permisos', $borrador->id, ['inicio' => ['modo' => 'cuando sea']])->assertUnprocessable();
    $this->postJson(urlPoliticas('permisos', "/borrador/{$borrador->id}/publicacion"), ['version' => 1, 'motivo' => 'm', 'revision' => ['revisor' => 'r']], ['Idempotency-Key' => (string) Str::uuid()])->assertUnprocessable();
    expect(DB::table('politica_versiones')->where('id', $borrador->id)->value('numero'))->toBeNull();
});

it('publicar con una versión programada existente exige retirarla primero, también de inmediato (PO-CA21)', function () {
    actuarComoAdminDeTenant();
    politicaPublicada('Permisos', ['inicio' => new DateTimeImmutable('+5 days', new DateTimeZone('UTC'))]);
    $borrador = borradorDe('Permisos');

    $inmediata = publicarApi($this, 'Permisos', $borrador->id)->assertConflict();
    $programada = publicarApi($this, 'Permisos', $borrador->id, ['inicio' => ['modo' => 'programado', 'fecha_hora_local' => inicioLocalManana()]])->assertConflict();

    expect($inmediata->json('code'))->toBe('PROGRAMADA_EXISTENTE')->and($programada->json('code'))->toBe('PROGRAMADA_EXISTENTE');
});

it('un borrador modificado entre la previsualización y la publicación responde 409 (PO-CA08)', function () {
    actuarComoAdminDeTenant();
    $borrador = borradorDe('Permisos');
    $this->patchJson(urlPoliticas('permisos', "/borrador/{$borrador->id}"), ['version' => 1, 'descripcion' => 'Cambio posterior'])->assertOk();

    $respuesta = publicarApi($this, 'Permisos', $borrador->id)->assertConflict();

    expect($respuesta->json('code'))->toBe('BORRADOR_MODIFICADO')->and(DB::table('politica_versiones')->where('id', $borrador->id)->value('numero'))->toBeNull();
});

it('un reintento con la misma clave devuelve la versión original sin duplicar (idempotencia)', function () {
    actuarComoAdminDeTenant();
    $borrador = borradorDe('Permisos');
    $clave = (string) Str::uuid();

    $primera = publicarApi($this, 'Permisos', $borrador->id, [], $clave)->assertCreated();
    $segunda = publicarApi($this, 'Permisos', $borrador->id, [], $clave)->assertOk();

    expect($segunda->json('data.id'))->toBe($primera->json('data.id'))->and($segunda->json('data.numero'))->toBe(1)
        ->and(DB::table('politica_acciones')->where('accion', 'Publicacion')->count())->toBe(1)
        ->and(DB::table('politicas_personal')->where('tipo', 'Permisos')->value('ultimo_numero'))->toBe(1);
});

it('la misma clave con otro contenido responde 409 y no aplica nada', function () {
    actuarComoAdminDeTenant();
    $borrador = borradorDe('Permisos');
    $clave = (string) Str::uuid();
    publicarApi($this, 'Permisos', $borrador->id, [], $clave)->assertCreated();

    $otroMotivo = publicarApi($this, 'Permisos', $borrador->id, ['motivo' => 'Otro motivo'], $clave)->assertConflict();
    $otraPolitica = publicarApi($this, 'Licencias', borradorDe('Licencias')->id, [], $clave)->assertConflict();

    expect($otroMotivo->json('code'))->toBe('CLAVE_DE_OPERACION_REUTILIZADA')->and($otraPolitica->json('code'))->toBe('CLAVE_DE_OPERACION_REUTILIZADA')
        ->and(DB::table('politica_versiones')->whereNotNull('numero')->count())->toBe(1);
});

it('exige la cabecera Idempotency-Key con un UUID válido', function () {
    actuarComoAdminDeTenant();
    $borrador = borradorDe('Permisos');
    $cuerpo = ['version' => 1, 'motivo' => 'm', 'revision' => ['revisor' => 'r'], 'inicio' => ['modo' => 'inmediato']];

    $sin = $this->postJson(urlPoliticas('permisos', "/borrador/{$borrador->id}/publicacion"), $cuerpo)->assertUnprocessable();
    $mala = $this->postJson(urlPoliticas('permisos', "/borrador/{$borrador->id}/publicacion"), $cuerpo, ['Idempotency-Key' => 'no-es-uuid'])->assertUnprocessable();

    expect($sin->json('code'))->toBe('CLAVE_DE_OPERACION_REQUERIDA')->and($mala->json('code'))->toBe('CLAVE_DE_OPERACION_REQUERIDA');
});

it('la numeración es correlativa por tipo y tenant y se registra la acción con el actor real', function () {
    $admin = actuarComoAdminDeTenant();

    $primera = publicarApi($this, 'Permisos', borradorDe('Permisos')->id)->assertCreated();
    $segunda = publicarApi($this, 'Permisos', $this->postJson(urlPoliticas('permisos', '/borrador'))->json('data.id'), [
        'version' => 1,
    ])->status();
    $licencias = publicarApi($this, 'Licencias', borradorDe('Licencias')->id)->assertCreated();

    expect($primera->json('data.numero'))->toBe(1)->and($segunda)->toBe(422)->and($licencias->json('data.numero'))->toBe(1);

    $accion = DB::table('politica_acciones')->where('accion', 'Publicacion')->where('version_id', $primera->json('data.id'))->first();
    expect((int) $accion->actor_id)->toBe($admin->id)->and($accion->motivo)->toBe('Revisión inicial de condiciones')
        ->and($accion->operacion_id)->not->toBeNull()->and($accion->operacion_hash)->toHaveLength(64);
});

it('publicar una segunda versión sustituye a la primera y ambas se conservan (PO-CA18)', function () {
    actuarComoAdminDeTenant();
    $v1 = publicarApi($this, 'Permisos', borradorDe('Permisos')->id)->assertCreated()->json('data.id');
    $nuevo = $this->postJson(urlPoliticas('permisos', '/borrador'), ['base_version_id' => $v1])->assertCreated()->json('data');
    $this->patchJson(urlPoliticas('permisos', "/borrador/{$nuevo['id']}"), [
        'version' => 1, 'parametros' => ['anticipacion_dias' => 7] + parametrosValidos('Permisos'),
    ])->assertOk();

    $v2 = publicarApi($this, 'Permisos', $nuevo['id'], ['motivo' => 'Subir la anticipación'], null, 2)->assertCreated();

    $detalle = $this->getJson(urlPoliticas('permisos'))->assertOk();
    $estados = collect($detalle->json('data.versiones'))->pluck('estado', 'numero')->all();
    expect($v2->json('data.numero'))->toBe(2)->and($estados)->toBe([2 => 'Vigente', 1 => 'Sustituida'])
        ->and($detalle->json('data.vigente.parametros.anticipacion_dias'))->toBe(7);
});

it('el interruptor apagado detiene la publicación con 503', function () {
    actuarComoAdminDeTenant();
    $borrador = borradorDe('Permisos');
    config(['hrms.gestion_personal.politicas_habilitado' => false]);

    publicarApi($this, 'Permisos', $borrador->id)->assertStatus(503)->assertJsonPath('code', 'ESCRITURA_DESHABILITADA');
    $this->postJson(urlPoliticas('licencias', '/borrador'))->assertStatus(503);
    $this->getJson(urlPoliticas('permisos'))->assertOk();
});
