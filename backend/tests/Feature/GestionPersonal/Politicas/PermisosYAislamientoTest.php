<?php

use Illuminate\Support\Facades\DB;

it('sin sesión responde 401 en todas las rutas', function () {
    $this->getJson(urlPoliticas())->assertUnauthorized();
    $this->getJson(urlPoliticas('permisos'))->assertUnauthorized();
    $this->postJson(urlPoliticas('permisos', '/borrador'))->assertUnauthorized();
});

it('el Colaborador no accede a ninguna ruta de administración (403)', function () {
    actuarComoUsuarioDeTenant();
    $borrador = borradorDe('Permisos');

    $this->getJson(urlPoliticas())->assertForbidden();
    $this->getJson(urlPoliticas('permisos'))->assertForbidden();
    $this->postJson(urlPoliticas('licencias', '/borrador'))->assertForbidden();
    $this->patchJson(urlPoliticas('permisos', "/borrador/{$borrador->id}"), ['version' => 1])->assertForbidden();
    $this->postJson(urlPoliticas('permisos', "/borrador/{$borrador->id}/descarte"), ['version' => 1])->assertForbidden();
    publicarApi($this, 'Permisos', $borrador->id)->assertForbidden();
    expect(DB::table('politica_versiones')->whereNotNull('publicada_en')->count())->toBe(0);
});

it('un tipo desconocido responde 404', function () {
    actuarComoAdminDeTenant();

    $this->getJson(urlPoliticas('inventada'))->assertNotFound();
    $this->postJson(urlPoliticas('inventada', '/borrador'))->assertNotFound();
});

it('una versión de otro tenant responde igual que una inexistente (404)', function () {
    actuarComoAdminDeTenant();
    tenantDos();
    $ajena = borradorDe('Permisos', [], 2);

    $inexistente = $this->patchJson(urlPoliticas('permisos', '/borrador/999999'), ['version' => 1, 'descripcion' => 'x'])->assertNotFound();
    $deOtroTenant = $this->patchJson(urlPoliticas('permisos', "/borrador/{$ajena->id}"), ['version' => 1, 'descripcion' => 'x'])->assertNotFound();
    $this->postJson(urlPoliticas('permisos', "/borrador/{$ajena->id}/descarte"), ['version' => 1])->assertNotFound();
    publicarApi($this, 'Permisos', $ajena->id)->assertNotFound();

    expect($deOtroTenant->json())->toBe($inexistente->json())
        ->and(DB::table('politica_versiones')->where('id', $ajena->id)->value('descartada_en'))->toBeNull();
});

it('cada tenant ve solo sus políticas y su numeración es independiente', function () {
    actuarComoAdminDeTenant();
    politicaPublicada('Permisos', ['tenant_id' => 2]);
    politicaPublicada('Licencias', ['tenant_id' => 1]);

    $lista = collect($this->getJson(urlPoliticas())->assertOk()->json('data'));

    expect($lista->firstWhere('tipo', 'permisos')['situacion'])->toBe('SinConfigurar')
        ->and($lista->firstWhere('tipo', 'licencias')['situacion'])->toBe('Vigente');

    $primera = publicarApi($this, 'Permisos', $this->postJson(urlPoliticas('permisos', '/borrador'))->json('data.id'), [], null, 1);
    expect($primera->status())->toBe(422);
});

it('el cuerpo no puede elegir tenant: el actor del tenant dos publica en el suyo', function () {
    $admin = actuarComoAdminDeTenant(2);
    $borrador = borradorDe('Permisos', [], 2);

    $respuesta = publicarApi($this, 'Permisos', $borrador->id, ['tenant_id' => 1])->assertCreated();

    expect($respuesta->json('data.numero'))->toBe(1)
        ->and((int) DB::table('politica_versiones')->where('id', $borrador->id)->value('tenant_id'))->toBe(2)
        ->and((int) DB::table('politica_acciones')->where('version_id', $borrador->id)->value('tenant_id'))->toBe(2)
        ->and((int) DB::table('politica_acciones')->where('version_id', $borrador->id)->value('actor_id'))->toBe($admin->id);
});
