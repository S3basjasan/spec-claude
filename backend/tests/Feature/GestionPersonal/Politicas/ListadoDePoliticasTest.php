<?php

use Illuminate\Support\Carbon;

afterEach(fn () => Carbon::setTestNow());

it('una organización nueva ve los siete tipos sin configurar y ninguno como sin restricciones (PO-CA01)', function () {
    actuarComoAdminDeTenant();

    $respuesta = $this->getJson(urlPoliticas())->assertOk();

    expect($respuesta->json('data'))->toHaveCount(7)
        ->and(array_column($respuesta->json('data'), 'tipo'))->toBe(['permisos', 'ausencias', 'licencias', 'vacaciones', 'ascensos', 'traslados', 'offboarding'])
        ->and(array_unique(array_column($respuesta->json('data'), 'situacion')))->toBe(['SinConfigurar'])
        ->and($respuesta->getContent())->not->toContain('Sin restricciones')
        ->and($respuesta->json('data.0.version_vigente'))->toBeNull()
        ->and($respuesta->json('data.0.tiene_borrador'))->toBeFalse();
});

it('muestra la versión vigente, la programada y el borrador de cada tipo', function () {
    actuarComoAdminDeTenant();
    politicaPublicada('Permisos', ['numero' => 1, 'inicio' => new DateTimeImmutable('-10 days', new DateTimeZone('UTC'))]);
    politicaPublicada('Permisos', ['inicio' => new DateTimeImmutable('+5 days', new DateTimeZone('UTC'))]);
    $borrador = borradorDe('Permisos');

    $permisos = collect($this->getJson(urlPoliticas())->assertOk()->json('data'))->firstWhere('tipo', 'permisos');

    expect($permisos['situacion'])->toBe('Vigente')->and($permisos['disponible'])->toBeTrue()
        ->and($permisos['version_vigente']['numero'])->toBe(1)
        ->and($permisos['version_programada']['numero'])->toBe(2)
        ->and($permisos['tiene_borrador'])->toBeTrue()->and($permisos['borrador_id'])->toBe($borrador->id);
});

it('una política vigente con disponibilidad apagada es NoAdmiteSolicitudes y no es lo mismo que sin configurar', function () {
    actuarComoAdminDeTenant();
    politicaPublicada('Licencias', ['parametros' => ['disponible' => false] + parametrosValidos('Licencias')]);

    $respuesta = $this->getJson(urlPoliticas())->assertOk()->json('data');
    $licencias = collect($respuesta)->firstWhere('tipo', 'licencias');

    expect($licencias['situacion'])->toBe('NoAdmiteSolicitudes')->and($licencias['disponible'])->toBeFalse()
        ->and(collect($respuesta)->firstWhere('tipo', 'permisos')['situacion'])->toBe('SinConfigurar');
});

it('Offboarding no tiene disponibilidad y una vigente siempre admite', function () {
    actuarComoAdminDeTenant();
    politicaPublicada('Offboarding');

    $offboarding = collect($this->getJson(urlPoliticas())->json('data'))->firstWhere('tipo', 'offboarding');

    expect($offboarding['situacion'])->toBe('Vigente')->and($offboarding['disponible'])->toBeNull();
});

it('una versión programada sola no configura el tipo hasta que comienza su vigencia', function () {
    actuarComoAdminDeTenant();
    Carbon::setTestNow(Carbon::parse('2026-10-20 12:00:00', 'UTC'));
    politicaPublicada('Vacaciones', ['inicio' => new DateTimeImmutable('2026-11-01 00:00:00', new DateTimeZone('UTC'))]);

    $antes = collect($this->getJson(urlPoliticas())->json('data'))->firstWhere('tipo', 'vacaciones');
    Carbon::setTestNow(Carbon::parse('2026-11-01 00:00:00', 'UTC'));
    $despues = collect($this->getJson(urlPoliticas())->json('data'))->firstWhere('tipo', 'vacaciones');

    expect($antes['situacion'])->toBe('SinConfigurar')->and($antes['version_programada']['numero'])->toBe(1)
        ->and($despues['situacion'])->toBe('Vigente')->and($despues['version_programada'])->toBeNull();
});

it('filtra por tipo y por situación', function () {
    actuarComoAdminDeTenant();
    politicaPublicada('Permisos');
    politicaPublicada('Ausencias', ['parametros' => ['disponible' => false] + parametrosValidos('Ausencias')]);
    politicaPublicada('Licencias', ['inicio' => new DateTimeImmutable('+5 days', new DateTimeZone('UTC'))]);

    $porTipo = $this->getJson(urlPoliticas().'?tipo=ausencias')->assertOk()->json('data');
    $vigentes = $this->getJson(urlPoliticas().'?situacion=Vigente')->assertOk()->json('data');
    $sinConfigurar = $this->getJson(urlPoliticas().'?situacion=SinConfigurar')->assertOk()->json('data');
    $conProgramada = $this->getJson(urlPoliticas().'?situacion=ConProgramada')->assertOk()->json('data');
    $noAdmite = $this->getJson(urlPoliticas().'?situacion=NoAdmiteSolicitudes')->assertOk()->json('data');

    expect(array_column($porTipo, 'tipo'))->toBe(['ausencias'])
        ->and(array_column($vigentes, 'tipo'))->toBe(['permisos'])
        ->and(array_column($noAdmite, 'tipo'))->toBe(['ausencias'])
        ->and(array_column($conProgramada, 'tipo'))->toBe(['licencias'])
        ->and($sinConfigurar)->toHaveCount(5)
        ->and($this->getJson(urlPoliticas().'?tipo=inventado')->assertUnprocessable()->json('code'))->toBe('VALIDACION');
});

it('el detalle de un tipo sin cabecera responde sin configurar con su definición', function () {
    actuarComoAdminDeTenant();

    $respuesta = $this->getJson(urlPoliticas('permisos'))->assertOk();

    expect($respuesta->json('data.resumen.situacion'))->toBe('SinConfigurar')
        ->and($respuesta->json('data.vigente'))->toBeNull()
        ->and($respuesta->json('data.versiones'))->toBe([]);
});
