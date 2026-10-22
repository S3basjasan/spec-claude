<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

afterEach(fn () => Carbon::setTestNow());

function retirarApi(mixed $prueba, string $tipo, int $version, array $cuerpo = ['justificacion' => 'Cambió el criterio de la organización'], ?string $clave = null)
{
    return $prueba->postJson(urlPoliticas($tipo, "/versiones/{$version}/retiro"), $cuerpo, ['Idempotency-Key' => $clave ?? (string) Str::uuid()]);
}

it('retira una programada: queda Retirada, la vigente sigue aplicando y el número no se reutiliza (PO-CA20)', function () {
    $admin = actuarComoAdminDeTenant();
    $v1 = politicaPublicada('Permisos', ['inicio' => new DateTimeImmutable('-10 days', new DateTimeZone('UTC'))]);
    $v2 = politicaPublicada('Permisos', ['inicio' => new DateTimeImmutable('+5 days', new DateTimeZone('UTC'))]);

    $respuesta = retirarApi($this, 'Permisos', $v2->id)->assertOk();

    expect($respuesta->json('data.estado'))->toBe('Retirada')->and($respuesta->json('data.retiro.por'))->toBe($admin->id)
        ->and($respuesta->json('data.retiro.motivo'))->toBe('Cambió el criterio de la organización')
        ->and($respuesta->json('data.retiro.en'))->not->toBeNull();

    $detalle = $this->getJson(urlPoliticas('permisos'))->assertOk();
    expect($detalle->json('data.vigente.id'))->toBe($v1->id)->and($detalle->json('data.programada'))->toBeNull()
        ->and(collect($detalle->json('data.versiones'))->pluck('estado', 'numero')->all())->toBe([2 => 'Retirada', 1 => 'Vigente']);

    $nuevo = $this->postJson(urlPoliticas('permisos', '/borrador'), ['base_version_id' => $v1->id])->assertCreated()->json('data.id');
    $tercera = publicarApi($this, 'Permisos', $nuevo)->assertCreated();
    expect($tercera->json('data.numero'))->toBe(3);
});

it('tras retirar se puede programar otra versión', function () {
    actuarComoAdminDeTenant();
    $programada = politicaPublicada('Licencias', ['inicio' => new DateTimeImmutable('+5 days', new DateTimeZone('UTC'))]);
    $borrador = borradorDe('Licencias');

    publicarApi($this, 'Licencias', $borrador->id)->assertConflict();
    retirarApi($this, 'Licencias', $programada->id)->assertOk();
    publicarApi($this, 'Licencias', $borrador->id)->assertCreated();
});

it('exige justificación y la limita a 500 caracteres', function () {
    actuarComoAdminDeTenant();
    $v = politicaPublicada('Permisos', ['inicio' => new DateTimeImmutable('+5 days', new DateTimeZone('UTC'))]);

    $vacia = retirarApi($this, 'Permisos', $v->id, ['justificacion' => '  '])->assertUnprocessable();
    retirarApi($this, 'Permisos', $v->id, ['justificacion' => str_repeat('j', 501)])->assertUnprocessable();
    retirarApi($this, 'Permisos', $v->id, [])->assertUnprocessable();

    expect($vacia->json('errors'))->toHaveKey('justificacion')->and(DB::table('politica_versiones')->where('id', $v->id)->value('retirada_en'))->toBeNull();
    retirarApi($this, 'Permisos', $v->id, ['justificacion' => str_repeat('j', 500)])->assertOk();
});

it('una versión que ya comenzó no se retira: 409 VIGENCIA_INICIADA (PO-CA22)', function () {
    actuarComoAdminDeTenant();
    $vigente = politicaPublicada('Permisos', ['inicio' => new DateTimeImmutable('-10 days', new DateTimeZone('UTC'))]);
    $sustituida = politicaPublicada('Permisos', ['inicio' => new DateTimeImmutable('-20 days', new DateTimeZone('UTC')), 'numero' => 5]);

    foreach ([$vigente, $sustituida] as $version) {
        expect(retirarApi($this, 'Permisos', $version->id)->assertConflict()->json('code'))->toBe('VIGENCIA_INICIADA');
    }
    expect(DB::table('politica_versiones')->whereNotNull('retirada_en')->count())->toBe(0);
});

it('un borrador o una versión ya retirada no son retirables: 409 VERSION_NO_RETIRABLE', function () {
    actuarComoAdminDeTenant();
    $borrador = borradorDe('Permisos');
    $retirada = politicaPublicada('Licencias', ['inicio' => new DateTimeImmutable('+5 days', new DateTimeZone('UTC')), 'retirada' => new DateTimeImmutable('now', new DateTimeZone('UTC'))]);

    expect(retirarApi($this, 'Permisos', $borrador->id)->assertConflict()->json('code'))->toBe('VERSION_NO_RETIRABLE')
        ->and(retirarApi($this, 'Licencias', $retirada->id)->assertConflict()->json('code'))->toBe('VERSION_NO_RETIRABLE');
});

it('el instante de inicio decide: un segundo antes se retira y en el instante exacto ya no', function () {
    actuarComoAdminDeTenant();
    $inicio = new DateTimeImmutable('2026-11-01 00:00:00', new DateTimeZone('UTC'));
    $v = politicaPublicada('Permisos', ['inicio' => $inicio, 'publicada' => new DateTimeImmutable('2026-10-01 00:00:00', new DateTimeZone('UTC'))]);

    Carbon::setTestNow(Carbon::parse('2026-11-01 00:00:00', 'UTC'));
    expect(retirarApi($this, 'Permisos', $v->id)->assertConflict()->json('code'))->toBe('VIGENCIA_INICIADA');

    Carbon::setTestNow(Carbon::parse('2026-10-31 23:59:59', 'UTC'));
    retirarApi($this, 'Permisos', $v->id)->assertOk();
});

it('el reintento con la misma clave es idempotente y con otro contenido o versión es 409', function () {
    actuarComoAdminDeTenant();
    $v = politicaPublicada('Permisos', ['inicio' => new DateTimeImmutable('+5 days', new DateTimeZone('UTC'))]);
    $otra = politicaPublicada('Licencias', ['inicio' => new DateTimeImmutable('+5 days', new DateTimeZone('UTC'))]);
    $clave = (string) Str::uuid();

    retirarApi($this, 'Permisos', $v->id, ['justificacion' => 'Motivo A'], $clave)->assertOk();
    $repetida = retirarApi($this, 'Permisos', $v->id, ['justificacion' => 'Motivo A'], $clave)->assertOk();
    $otroContenido = retirarApi($this, 'Permisos', $v->id, ['justificacion' => 'Motivo B'], $clave)->assertConflict();
    $otraVersion = retirarApi($this, 'Licencias', $otra->id, ['justificacion' => 'Motivo A'], $clave)->assertConflict();

    expect($repetida->json('data.estado'))->toBe('Retirada')
        ->and($otroContenido->json('code'))->toBe('CLAVE_DE_OPERACION_REUTILIZADA')->and($otraVersion->json('code'))->toBe('CLAVE_DE_OPERACION_REUTILIZADA')
        ->and(DB::table('politica_acciones')->where('accion', 'Retiro')->count())->toBe(1)
        ->and(DB::table('politica_versiones')->where('id', $otra->id)->value('retirada_en'))->toBeNull();
});

it('exige la clave de operación y el interruptor la detiene; 404 para ajenas', function () {
    actuarComoAdminDeTenant();
    $v = politicaPublicada('Permisos', ['inicio' => new DateTimeImmutable('+5 days', new DateTimeZone('UTC'))]);
    $ajena = politicaPublicada('Permisos', ['tenant_id' => 2]);

    $this->postJson(urlPoliticas('permisos', "/versiones/{$v->id}/retiro"), ['justificacion' => 'x'])->assertUnprocessable()->assertJsonPath('code', 'CLAVE_DE_OPERACION_REQUERIDA');
    retirarApi($this, 'Permisos', $ajena->id)->assertNotFound();
    retirarApi($this, 'Permisos', 999999)->assertNotFound();

    config(['hrms.gestion_personal.politicas_habilitado' => false]);
    retirarApi($this, 'Permisos', $v->id)->assertStatus(503)->assertJsonPath('code', 'ESCRITURA_DESHABILITADA');
});

it('el retiro registra responsable, momento y justificación en la auditoría', function () {
    $admin = actuarComoAdminDeTenant();
    $v = politicaPublicada('Permisos', ['inicio' => new DateTimeImmutable('+5 days', new DateTimeZone('UTC'))]);

    retirarApi($this, 'Permisos', $v->id, ['justificacion' => '  Retiro por revisión legal  '])->assertOk();

    $accion = DB::table('politica_acciones')->where('accion', 'Retiro')->first();
    expect((int) $accion->actor_id)->toBe($admin->id)->and($accion->motivo)->toBe('Retiro por revisión legal')
        ->and($accion->created_at)->not->toBeNull()->and($accion->operacion_hash)->toHaveLength(64);
});
