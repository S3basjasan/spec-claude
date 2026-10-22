<?php

use Illuminate\Support\Facades\DB;

it('crea un borrador sin número y registra la acción', function () {
    $admin = actuarComoAdminDeTenant();

    $respuesta = $this->postJson(urlPoliticas('permisos', '/borrador'))->assertCreated();

    expect($respuesta->json('data.estado'))->toBe('Borrador')->and($respuesta->json('data.numero'))->toBeNull()
        ->and($respuesta->json('data.version'))->toBe(1)->and($respuesta->json('data.created_by'))->toBe($admin->id)
        ->and($respuesta->json('data.inicio_vigencia'))->toBeNull()
        ->and(DB::table('politica_acciones')->where('accion', 'BorradorCreado')->where('actor_id', $admin->id)->count())->toBe(1);
});

it('un segundo borrador del mismo tipo responde 409 con el id del existente (PO-CA08)', function () {
    actuarComoAdminDeTenant();
    $primero = $this->postJson(urlPoliticas('permisos', '/borrador'))->assertCreated()->json('data.id');

    $respuesta = $this->postJson(urlPoliticas('permisos', '/borrador'))->assertConflict();

    expect($respuesta->json('code'))->toBe('BORRADOR_EXISTENTE')->and($respuesta->json('errors.borrador_id'))->toBe($primero);
    $this->postJson(urlPoliticas('licencias', '/borrador'))->assertCreated();
});

it('crea el borrador a partir de una versión publicada copiando sus datos', function () {
    actuarComoAdminDeTenant();
    $base = politicaPublicada('Permisos', ['descripcion' => 'Base vigente']);

    $respuesta = $this->postJson(urlPoliticas('permisos', '/borrador'), ['base_version_id' => $base->id])->assertCreated();

    expect($respuesta->json('data.descripcion'))->toBe('Base vigente')
        ->and($respuesta->json('data.parametros.anticipacion_dias'))->toBe(2)
        ->and($respuesta->json('data.base_version_id'))->toBe($base->id);
});

it('la versión base debe ser una versión publicada del mismo tipo', function () {
    actuarComoAdminDeTenant();
    $otroTipo = politicaPublicada('Licencias');

    $this->postJson(urlPoliticas('permisos', '/borrador'), ['base_version_id' => $otroTipo->id])->assertUnprocessable()->assertJsonPath('code', 'VALIDACION');
    $this->postJson(urlPoliticas('permisos', '/borrador'), ['base_version_id' => 999999])->assertUnprocessable();
    expect(DB::table('politica_versiones')->whereNull('publicada_en')->count())->toBe(0);
});

it('edita con la versión correcta e incrementa la versión del borrador', function () {
    $admin = actuarComoAdminDeTenant();
    $borrador = borradorDe('Permisos', ['parametros' => []]);

    $respuesta = $this->patchJson(urlPoliticas('permisos', "/borrador/{$borrador->id}"), [
        'version' => 1, 'descripcion' => '  Permisos de la organización  ', 'parametros' => ['disponible' => true, 'anticipacion_dias' => 3],
    ])->assertOk();

    expect($respuesta->json('data.version'))->toBe(2)->and($respuesta->json('data.descripcion'))->toBe('Permisos de la organización')
        ->and($respuesta->json('data.parametros'))->toEqual(['disponible' => true, 'anticipacion_dias' => 3])
        ->and($respuesta->json('data.updated_by'))->toBe($admin->id);
});

it('editar con una versión obsoleta responde 409 sin cambiar nada (PO-CA08)', function () {
    actuarComoAdminDeTenant();
    $borrador = borradorDe('Permisos');
    $this->patchJson(urlPoliticas('permisos', "/borrador/{$borrador->id}"), ['version' => 1, 'descripcion' => 'Primero'])->assertOk();

    $respuesta = $this->patchJson(urlPoliticas('permisos', "/borrador/{$borrador->id}"), ['version' => 1, 'descripcion' => 'Segundo']);

    expect($respuesta->status())->toBe(409)->and($respuesta->json('code'))->toBe('BORRADOR_MODIFICADO')
        ->and(DB::table('politica_versiones')->where('id', $borrador->id)->value('descripcion'))->toBe('Primero');
});

it('guardar un borrador incompleto es válido; solo se rechazan los valores inválidos', function () {
    actuarComoAdminDeTenant();
    $borrador = borradorDe('Permisos', ['parametros' => []]);

    $this->patchJson(urlPoliticas('permisos', "/borrador/{$borrador->id}"), ['version' => 1, 'descripcion' => ''])->assertOk();
    $this->patchJson(urlPoliticas('permisos', "/borrador/{$borrador->id}"), ['version' => 2, 'parametros' => ['anticipacion_dias' => -1]])
        ->assertUnprocessable()->assertJsonStructure(['errors' => ['parametros.anticipacion_dias']]);
});

it('descarta el borrador, registra la acción y permite crear otro', function () {
    actuarComoAdminDeTenant();
    $borrador = borradorDe('Permisos');

    $this->postJson(urlPoliticas('permisos', "/borrador/{$borrador->id}/descarte"), ['version' => 1])->assertOk();

    expect(DB::table('politica_versiones')->where('id', $borrador->id)->value('descartada_en'))->not->toBeNull()
        ->and(DB::table('politica_acciones')->where('accion', 'BorradorDescartado')->count())->toBe(1);
    $this->postJson(urlPoliticas('permisos', '/borrador'))->assertCreated();
});

it('un borrador descartado, publicado o ajeno no se edita ni se descarta', function () {
    actuarComoAdminDeTenant();
    $descartado = borradorDe('Permisos');
    $this->postJson(urlPoliticas('permisos', "/borrador/{$descartado->id}/descarte"), ['version' => 1])->assertOk();
    $publicada = politicaPublicada('Licencias');

    $this->patchJson(urlPoliticas('permisos', "/borrador/{$descartado->id}"), ['version' => 1, 'descripcion' => 'x'])->assertConflict();
    $this->postJson(urlPoliticas('permisos', "/borrador/{$descartado->id}/descarte"), ['version' => 1])->assertConflict();
    $this->patchJson(urlPoliticas('licencias', "/borrador/{$publicada->id}"), ['version' => 1, 'descripcion' => 'x'])->assertConflict();
    $this->patchJson(urlPoliticas('permisos', "/borrador/{$publicada->id}"), ['version' => 1, 'descripcion' => 'x'])->assertNotFound();
    $this->patchJson(urlPoliticas('permisos', '/borrador/999999'), ['version' => 1, 'descripcion' => 'x'])->assertNotFound();
});

it('el cuerpo no puede elegir tenant ni actor ni número', function () {
    $admin = actuarComoAdminDeTenant();
    $borrador = borradorDe('Permisos');

    $this->patchJson(urlPoliticas('permisos', "/borrador/{$borrador->id}"), [
        'version' => 1, 'descripcion' => 'x', 'tenant_id' => 2, 'created_by' => 99, 'numero' => 7, 'publicada_en' => now()->toIso8601String(),
    ])->assertOk();

    $fila = DB::table('politica_versiones')->where('id', $borrador->id)->first();
    expect((int) $fila->tenant_id)->toBe(1)->and((int) $fila->created_by)->toBe(1)->and($fila->numero)->toBeNull()
        ->and($fila->publicada_en)->toBeNull()->and((int) $fila->updated_by)->toBe($admin->id);
});
