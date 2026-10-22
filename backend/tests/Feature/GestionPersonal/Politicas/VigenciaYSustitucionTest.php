<?php

use Illuminate\Support\Carbon;

afterEach(fn () => Carbon::setTestNow());

function estadosDe(mixed $prueba, string $tipo): array
{
    return collect($prueba->getJson(urlPoliticas($tipo))->assertOk()->json('data.versiones'))->pluck('estado', 'numero')->all();
}

it('una programada comienza sola al llegar su inicio y sustituye a la anterior, sin proceso auxiliar (PO-CA18)', function () {
    actuarComoAdminDeTenant();
    Carbon::setTestNow(Carbon::parse('2026-10-20 12:00:00', 'UTC'));
    politicaPublicada('Permisos', ['inicio' => new DateTimeImmutable('2026-10-01 00:00:00', new DateTimeZone('UTC'))]);
    politicaPublicada('Permisos', ['inicio' => new DateTimeImmutable('2026-11-01 00:00:00', new DateTimeZone('UTC')), 'publicada' => new DateTimeImmutable('2026-10-15 00:00:00', new DateTimeZone('UTC'))]);

    expect(estadosDe($this, 'permisos'))->toBe([2 => 'Programada', 1 => 'Vigente']);

    Carbon::setTestNow(Carbon::parse('2026-10-31 23:59:59', 'UTC'));
    expect(estadosDe($this, 'permisos'))->toBe([2 => 'Programada', 1 => 'Vigente']);

    Carbon::setTestNow(Carbon::parse('2026-11-01 00:00:00', 'UTC'));
    expect(estadosDe($this, 'permisos'))->toBe([2 => 'Vigente', 1 => 'Sustituida']);
});

it('el estado es correcto aunque ningún planificador haya corrido y al adelantar el reloj varias versiones', function () {
    actuarComoAdminDeTenant();
    Carbon::setTestNow(Carbon::parse('2026-01-01 00:00:00', 'UTC'));
    foreach (['2026-02-01', '2026-03-01', '2026-04-01'] as $i => $fecha) {
        politicaPublicada('Licencias', ['inicio' => new DateTimeImmutable("$fecha 00:00:00", new DateTimeZone('UTC')), 'publicada' => new DateTimeImmutable('2026-01-01 00:00:00', new DateTimeZone('UTC'))]);
    }

    Carbon::setTestNow(Carbon::parse('2026-03-15 00:00:00', 'UTC'));
    $detalle = $this->getJson(urlPoliticas('licencias'))->assertOk();

    expect(collect($detalle->json('data.versiones'))->pluck('estado', 'numero')->all())->toBe([3 => 'Programada', 2 => 'Vigente', 1 => 'Sustituida'])
        ->and($detalle->json('data.vigente.numero'))->toBe(2)->and($detalle->json('data.programada.numero'))->toBe(3);
});

it('un tipo con solo una programada sigue sin configurar hasta que comience (PO-CA31)', function () {
    actuarComoAdminDeTenant();
    Carbon::setTestNow(Carbon::parse('2026-10-20 12:00:00', 'UTC'));
    politicaPublicada('Vacaciones', ['inicio' => new DateTimeImmutable('2026-12-01 00:00:00', new DateTimeZone('UTC'))]);

    expect($this->getJson(urlPoliticas('vacaciones'))->json('data.resumen.situacion'))->toBe('SinConfigurar');

    Carbon::setTestNow(Carbon::parse('2026-12-01 00:00:01', 'UTC'));
    expect($this->getJson(urlPoliticas('vacaciones'))->json('data.resumen.situacion'))->toBe('Vigente');
});

it('programar y avanzar el reloj por la API produce la sustitución y conserva ambas versiones', function () {
    actuarComoAdminDeTenant();
    Carbon::setTestNow(Carbon::parse('2026-10-20 12:00:00', 'UTC'));
    $v1 = publicarApi($this, 'Permisos', borradorDe('Permisos')->id)->assertCreated()->json('data.id');
    $nuevo = $this->postJson(urlPoliticas('permisos', '/borrador'), ['base_version_id' => $v1])->assertCreated()->json('data.id');
    $this->patchJson(urlPoliticas('permisos', "/borrador/{$nuevo}"), ['version' => 1, 'parametros' => ['anticipacion_dias' => 9] + parametrosValidos('Permisos')])->assertOk();
    $v2 = publicarApi($this, 'Permisos', $nuevo, ['inicio' => ['modo' => 'programado', 'fecha_hora_local' => '2026-11-05T08:00']], null, 2)->assertCreated();

    expect($v2->json('data.estado'))->toBe('Programada')->and($v2->json('data.inicio_vigencia'))->toBe('2026-11-05T13:00:00Z');

    Carbon::setTestNow(Carbon::parse('2026-11-05 13:00:00', 'UTC'));
    $detalle = $this->getJson(urlPoliticas('permisos'))->assertOk();

    expect(collect($detalle->json('data.versiones'))->pluck('estado', 'numero')->all())->toBe([2 => 'Vigente', 1 => 'Sustituida'])
        ->and($detalle->json('data.vigente.parametros.anticipacion_dias'))->toBe(9)
        ->and($this->getJson(urlPoliticas('permisos', "/versiones/{$v1}"))->json('data.parametros.anticipacion_dias'))->toBe(2);
});

it('el inicio programado se interpreta en la zona horaria de la organización, también con cambio de hora', function () {
    actuarComoAdminDeTenant();
    tenantConZona('America/New_York');
    Carbon::setTestNow(Carbon::parse('2026-03-01 00:00:00', 'UTC'));
    $borrador = borradorDe('Permisos');

    $respuesta = publicarApi($this, 'Permisos', $borrador->id, ['inicio' => ['modo' => 'programado', 'fecha_hora_local' => '2026-03-08T02:30']])->assertCreated();

    expect($respuesta->json('data.inicio_vigencia'))->toBe('2026-03-08T07:00:00Z')->and($respuesta->json('data.inicio_vigencia_local'))->toBe('2026-03-08T03:00');
});
