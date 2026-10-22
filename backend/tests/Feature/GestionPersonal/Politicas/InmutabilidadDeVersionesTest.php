<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('no existe ninguna ruta para editar ni eliminar una versión publicada (PO-CA19)', function () {
    actuarComoAdminDeTenant();
    $v = politicaPublicada('Permisos');

    $this->deleteJson(urlPoliticas('permisos', "/versiones/{$v->id}"))->assertStatus(405);
    $this->putJson(urlPoliticas('permisos', "/versiones/{$v->id}"), ['descripcion' => 'x'])->assertStatus(405);
    $this->patchJson(urlPoliticas('permisos', "/versiones/{$v->id}"), ['descripcion' => 'x'])->assertStatus(405);
    $this->deleteJson(urlPoliticas('permisos', "/borrador/{$v->id}"))->assertStatus(405);
    expect(DB::table('politica_versiones')->where('id', $v->id)->value('descripcion'))->toBe('Política de Permisos');
});

it('las rutas de borrador rechazan una versión publicada y no la modifican', function () {
    actuarComoAdminDeTenant();
    $v = politicaPublicada('Permisos');
    $antes = (array) DB::table('politica_versiones')->where('id', $v->id)->first();

    $this->patchJson(urlPoliticas('permisos', "/borrador/{$v->id}"), ['version' => 1, 'descripcion' => 'x', 'parametros' => ['anticipacion_dias' => 9]])->assertConflict();
    $this->postJson(urlPoliticas('permisos', "/borrador/{$v->id}/descarte"), ['version' => 1])->assertConflict();
    publicarApi($this, 'Permisos', $v->id)->assertConflict();

    expect((array) DB::table('politica_versiones')->where('id', $v->id)->first())->toBe($antes);
});

it('una escritura directa sobre una versión publicada falla por el trigger', function () {
    $v = politicaPublicada('Permisos');

    expect(fn () => DB::table('politica_versiones')->where('id', $v->id)->update(['parametros' => '{}']))->toThrow(QueryException::class)
        ->and(fn () => DB::table('politica_versiones')->where('id', $v->id)->update(['inicio_vigencia' => '2030-01-01 00:00:00']))->toThrow(QueryException::class)
        ->and(fn () => DB::table('politica_versiones')->where('id', $v->id)->delete())->toThrow(QueryException::class);
});

it('crear un borrador a partir de una publicada no la modifica y su hash sellado no cambia al publicar otras', function () {
    actuarComoAdminDeTenant();
    $v1 = publicarApi($this, 'Permisos', borradorDe('Permisos')->id)->assertCreated()->json('data.id');
    $hash1 = DB::table('politica_versiones')->where('id', $v1)->value('condiciones_hash');
    $condiciones1 = DB::table('politica_versiones')->where('id', $v1)->value('condiciones');

    $nuevo = $this->postJson(urlPoliticas('permisos', '/borrador'), ['base_version_id' => $v1])->assertCreated()->json('data.id');
    $this->patchJson(urlPoliticas('permisos', "/borrador/{$nuevo}"), ['version' => 1, 'parametros' => ['anticipacion_dias' => 10] + parametrosValidos('Permisos')])->assertOk();
    publicarApi($this, 'Permisos', $nuevo, [], null, 2)->assertCreated();

    expect(DB::table('politica_versiones')->where('id', $v1)->value('condiciones_hash'))->toBe($hash1)
        ->and(DB::table('politica_versiones')->where('id', $v1)->value('condiciones'))->toBe($condiciones1)
        ->and(hash('sha256', $condiciones1))->toBe($hash1);
});

it('las versiones publicadas nunca se eliminan: el listado conserva el historial completo', function () {
    actuarComoAdminDeTenant();
    foreach ([1, 2, 3] as $i) {
        politicaPublicada('Ausencias', ['inicio' => new DateTimeImmutable("-{$i} days", new DateTimeZone('UTC'))]);
    }

    expect($this->getJson(urlPoliticas('ausencias'))->json('data.versiones'))->toHaveCount(3);
});
