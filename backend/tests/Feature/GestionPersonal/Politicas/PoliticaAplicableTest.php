<?php

use App\Modules\GestionPersonal\Domain\Entities\PoliticaDePersonal;
use App\Modules\GestionPersonal\Domain\Repositories\PoliticaRepositoryInterface;
use App\Modules\GestionPersonal\Domain\ValueObjects\TipoDePolitica;
use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Repositories\EloquentPoliticaRepository;

it('sin versión vigente la situación es SinConfigurar, sin número ni parámetros', function () {
    actuarComoUsuarioDeTenant();

    $respuesta = $this->getJson(urlPoliticas('permisos', '/aplicable'))->assertOk();

    expect($respuesta->json('data.situacion'))->toBe('SinConfigurar')->and($respuesta->json('data.numero'))->toBeNull()
        ->and($respuesta->json('data.parametros'))->toBeNull()->and($respuesta->json('data.inicio_vigencia'))->toBeNull()
        ->and($respuesta->json('data.reglas_fijas'))->not->toBeEmpty();
});

it('con una vigente devuelve número, inicio, parámetros y reglas fijas, y con disponible apagado NoAdmiteSolicitudes', function () {
    actuarComoUsuarioDeTenant();
    politicaPublicada('Permisos');
    politicaPublicada('Licencias', ['parametros' => ['disponible' => false] + parametrosValidos('Licencias')]);

    $permisos = $this->getJson(urlPoliticas('permisos', '/aplicable'))->assertOk();
    $licencias = $this->getJson(urlPoliticas('licencias', '/aplicable'))->assertOk();

    expect($permisos->json('data.situacion'))->toBe('Vigente')->and($permisos->json('data.numero'))->toBe(1)
        ->and($permisos->json('data.parametros.anticipacion_dias'))->toBe(2)->and($permisos->json('data.inicio_vigencia'))->not->toBeNull()
        ->and(array_column($permisos->json('data.reglas_fijas'), 'clave'))->toContain('NOV-RESUELVE-ADMIN')
        ->and($licencias->json('data.situacion'))->toBe('NoAdmiteSolicitudes')->and($licencias->json('data.parametros.disponible'))->toBeFalse();
});

it('con una programada futura devuelve la vigente y no expone nada interno (PO-CA34)', function () {
    actuarComoUsuarioDeTenant();
    politicaPublicada('Permisos', ['descripcion' => 'DESCRIPCION-INTERNA', 'inicio' => new DateTimeImmutable('-10 days', new DateTimeZone('UTC'))]);
    politicaPublicada('Permisos', ['descripcion' => 'PROGRAMADA-SECRETA', 'inicio' => new DateTimeImmutable('+10 days', new DateTimeZone('UTC'))]);
    borradorDe('Permisos', ['descripcion' => 'BORRADOR-SECRETO']);

    $respuesta = $this->getJson(urlPoliticas('permisos', '/aplicable'))->assertOk();
    $cuerpo = $respuesta->getContent();

    expect($respuesta->json('data.numero'))->toBe(1)
        ->and(array_keys($respuesta->json('data')))->toBe(['tipo', 'situacion', 'numero', 'inicio_vigencia', 'parametros', 'reglas_fijas'])
        ->and($cuerpo)->not->toContain('DESCRIPCION-INTERNA')->not->toContain('PROGRAMADA-SECRETA')->not->toContain('BORRADOR-SECRETO')
        ->not->toContain('motivo')->not->toContain('revisor')->not->toContain('publicada_por');
});

it('el Colaborador consulta las condiciones pero no el panel (PO-CA34)', function () {
    actuarComoUsuarioDeTenant();
    politicaPublicada('Permisos');

    $this->getJson(urlPoliticas('permisos', '/aplicable'))->assertOk();
    $this->getJson(urlPoliticas())->assertForbidden();
    $this->getJson(urlPoliticas('permisos'))->assertForbidden();
});

it('sin sesión responde 401, un tipo desconocido 404 y cada tenant ve solo lo suyo', function () {
    $this->getJson(urlPoliticas('permisos', '/aplicable'))->assertUnauthorized();

    actuarComoUsuarioDeTenant();
    politicaPublicada('Permisos', ['tenant_id' => 2]);

    $this->getJson(urlPoliticas('inventada', '/aplicable'))->assertNotFound();
    expect($this->getJson(urlPoliticas('permisos', '/aplicable'))->assertOk()->json('data.situacion'))->toBe('SinConfigurar');
});

it('una falla de lectura responde 503 POLITICA_NO_DISPONIBLE y nunca un cuerpo sin requisitos (PO-CA32)', function () {
    actuarComoUsuarioDeTenant();
    politicaPublicada('Permisos');
    app()->bind(PoliticaRepositoryInterface::class, fn () => new class extends EloquentPoliticaRepository
    {
        public function cabecera(int $tenantId, TipoDePolitica $tipo): ?PoliticaDePersonal
        {
            throw new RuntimeException('base de datos caída');
        }
    });

    $respuesta = $this->getJson(urlPoliticas('permisos', '/aplicable'))->assertStatus(503);

    expect($respuesta->json('code'))->toBe('POLITICA_NO_DISPONIBLE')->and($respuesta->json('data'))->toBeNull()
        ->and($respuesta->getContent())->not->toContain('SinConfigurar');
});

it('la consulta es informativa: no escribe ni reserva ninguna versión (PO-CA27)', function () {
    actuarComoUsuarioDeTenant();
    politicaPublicada('Permisos');
    $escrituras = [];
    DB::listen(function ($consulta) use (&$escrituras) {
        if (preg_match('/^\s*(insert|update|delete)/i', $consulta->sql) === 1) {
            $escrituras[] = $consulta->sql;
        }
    });

    $this->getJson(urlPoliticas('permisos', '/aplicable'))->assertOk();

    expect($escrituras)->toBe([]);
});
