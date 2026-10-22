<?php

use Illuminate\Support\Facades\DB;

it('compara una propuesta con la vigente real aunque haya nacido de una versión sustituida', function () {
    actuarComoAdminDeTenant();
    $v1 = politicaPublicada('Permisos', ['inicio' => new DateTimeImmutable('-30 days', new DateTimeZone('UTC')), 'parametros' => ['anticipacion_dias' => 1] + parametrosValidos('Permisos')]);
    politicaPublicada('Permisos', ['inicio' => new DateTimeImmutable('-10 days', new DateTimeZone('UTC')), 'parametros' => ['anticipacion_dias' => 5] + parametrosValidos('Permisos')]);
    $borrador = $this->postJson(urlPoliticas('permisos', '/borrador'), ['base_version_id' => $v1->id])->assertCreated()->json('data.id');

    $respuesta = $this->getJson(urlPoliticas('permisos', "/versiones/{$borrador}/comparacion"))->assertOk();

    expect($respuesta->json('data.contra.numero'))->toBe(2)
        ->and(collect($respuesta->json('data.diferencias'))->firstWhere('clave', 'anticipacion_dias'))->toMatchArray(['anterior' => 5, 'nuevo' => 1]);
});

it('sin versión vigente la comparación no tiene contra y marca todo como nuevo', function () {
    actuarComoAdminDeTenant();
    $borrador = borradorDe('Traslados');

    $respuesta = $this->getJson(urlPoliticas('traslados', "/versiones/{$borrador->id}/comparacion"))->assertOk();

    expect($respuesta->json('data.contra'))->toBeNull()->and(array_column($respuesta->json('data.diferencias'), 'clave'))->toContain('anticipacion_dias', 'disponible');
});

it('el historial se ordena por número con parámetros, vigencia, motivo, revisión y responsables (PO-CA25)', function () {
    $admin = actuarComoAdminDeTenant();
    foreach ([30, 20, 10] as $dias) {
        politicaPublicada('Vacaciones', ['inicio' => new DateTimeImmutable("-{$dias} days", new DateTimeZone('UTC'))]);
    }

    $versiones = $this->getJson(urlPoliticas('vacaciones'))->assertOk()->json('data.versiones');

    expect(array_column($versiones, 'numero'))->toBe([3, 2, 1])
        ->and($versiones[0])->toHaveKeys(['parametros', 'inicio_vigencia', 'motivo_cambio', 'revision', 'publicada_por', 'publicada_en'])
        ->and($versiones[0]['revision']['revisor'])->toBe('Revisor de prueba')
        ->and($versiones[0]['publicada_por'])->toBe(1)
        ->and($admin->id)->toBeInt();
});

it('el detalle de una versión publicada y el de una inexistente o ajena', function () {
    actuarComoAdminDeTenant();
    $v = politicaPublicada('Permisos');
    $ajena = politicaPublicada('Permisos', ['tenant_id' => 2]);

    $this->getJson(urlPoliticas('permisos', "/versiones/{$v->id}"))->assertOk()->assertJsonPath('data.numero', 1);
    $this->getJson(urlPoliticas('licencias', "/versiones/{$v->id}"))->assertNotFound();
    $this->getJson(urlPoliticas('permisos', "/versiones/{$ajena->id}"))->assertNotFound();
    $this->getJson(urlPoliticas('permisos', '/versiones/999999'))->assertNotFound();
});

it('el listado no incluye borradores descartados ni el borrador entre las versiones publicadas', function () {
    actuarComoAdminDeTenant();
    politicaPublicada('Permisos');
    $descartado = borradorDe('Permisos');
    $this->postJson(urlPoliticas('permisos', "/borrador/{$descartado->id}/descarte"), ['version' => 1])->assertOk();
    $activo = borradorDe('Permisos');

    $detalle = $this->getJson(urlPoliticas('permisos'))->assertOk();

    expect($detalle->json('data.versiones'))->toHaveCount(1)->and($detalle->json('data.borrador.id'))->toBe($activo->id)
        ->and($this->getJson(urlPoliticas('permisos', "/versiones/{$descartado->id}"))->status())->toBe(404);
});

it('las acciones de auditoría guardan actor, momento y motivo del ciclo completo', function () {
    $admin = actuarComoAdminDeTenant();
    $id = $this->postJson(urlPoliticas('permisos', '/borrador'))->assertCreated()->json('data.id');
    $this->patchJson(urlPoliticas('permisos', "/borrador/{$id}"), ['version' => 1, 'descripcion' => 'D', 'parametros' => parametrosValidos('Permisos')])->assertOk();
    publicarApi($this, 'Permisos', $id, ['motivo' => 'Primera política'], null, 2)->assertCreated();

    $acciones = DB::table('politica_acciones')->orderBy('id')->get();

    expect($acciones->pluck('accion')->all())->toBe(['BorradorCreado', 'Publicacion'])
        ->and($acciones->pluck('actor_id')->unique()->all())->toBe([$admin->id])
        ->and($acciones[1]->motivo)->toBe('Primera política')
        ->and($acciones->every(fn ($a) => $a->created_at !== null))->toBeTrue();
});
