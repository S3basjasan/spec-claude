<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

function accionDePoliticaDePrueba(int $politicaId, int $versionId, array $extra = []): int
{
    return DB::table('politica_acciones')->insertGetId(array_merge([
        'tenant_id' => 1, 'politica_id' => $politicaId, 'version_id' => $versionId, 'accion' => 'Publicacion',
        'actor_id' => 1, 'created_at' => now(),
    ], $extra));
}

it('una versión publicada no admite cambios en columnas selladas ni eliminarse', function () {
    $v = politicaPublicada('Permisos');

    expect(fn () => DB::table('politica_versiones')->where('id', $v->id)->update(['descripcion' => 'otra']))->toThrow(QueryException::class)
        ->and(fn () => DB::table('politica_versiones')->where('id', $v->id)->update(['condiciones' => '{}']))->toThrow(QueryException::class)
        ->and(fn () => DB::table('politica_versiones')->where('id', $v->id)->update(['numero' => 99]))->toThrow(QueryException::class)
        ->and(fn () => DB::table('politica_versiones')->where('id', $v->id)->delete())->toThrow(QueryException::class);
});

it('tras publicar solo cambian el retiro y la marca de actualización', function () {
    $v = politicaPublicada('Permisos', ['inicio' => new DateTimeImmutable('+3 days', new DateTimeZone('UTC'))]);

    DB::table('politica_versiones')->where('id', $v->id)->update([
        'retirada_en' => now(), 'retirada_por' => 1, 'retiro_motivo' => 'Ya no aplica', 'updated_by' => 1, 'updated_at' => now(),
    ]);

    expect(DB::table('politica_versiones')->where('id', $v->id)->value('retiro_motivo'))->toBe('Ya no aplica');
});

it('el retiro exige sus tres columnas juntas y solo en una versión publicada', function () {
    $v = politicaPublicada('Permisos');
    $b = borradorDe('Licencias');

    expect(fn () => DB::table('politica_versiones')->where('id', $v->id)->update(['retirada_en' => now()]))->toThrow(QueryException::class)
        ->and(fn () => DB::table('politica_versiones')->where('id', $b->id)->update(['retirada_en' => now(), 'retirada_por' => 1, 'retiro_motivo' => 'x']))->toThrow(QueryException::class);
});

it('las columnas de publicación son todas nulas o todas no nulas', function () {
    $b = borradorDe('Permisos');

    expect(fn () => DB::table('politica_versiones')->where('id', $b->id)->update(['numero' => 1]))->toThrow(QueryException::class)
        ->and(fn () => DB::table('politica_versiones')->where('id', $b->id)->update(['publicada_en' => now()]))->toThrow(QueryException::class);
});

it('solo un borrador activo por tipo y se permite otro tras descartar', function () {
    $primero = borradorDe('Permisos');

    expect(fn () => borradorDe('Permisos'))->toThrow(QueryException::class);

    DB::table('politica_versiones')->where('id', $primero->id)->update(['descartada_en' => now(), 'descartada_por' => 1]);

    expect(borradorDe('Permisos')->id)->toBeInt()
        ->and(fn () => DB::table('politica_versiones')->where('id', $primero->id)->update(['descripcion' => 'x']))->toThrow(QueryException::class);
});

it('el número de publicación es único por política', function () {
    politicaPublicada('Permisos', ['numero' => 1]);

    expect(fn () => politicaPublicada('Permisos', ['numero' => 1]))->toThrow(QueryException::class);
});

it('la auditoría es de solo inserción', function () {
    $v = politicaPublicada('Permisos');
    $id = accionDePoliticaDePrueba($v->politica_id, $v->id);

    expect(fn () => DB::table('politica_acciones')->where('id', $id)->update(['motivo' => 'x']))->toThrow(QueryException::class)
        ->and(fn () => DB::table('politica_acciones')->where('id', $id)->delete())->toThrow(QueryException::class)
        ->and(fn () => accionDePoliticaDePrueba($v->politica_id, $v->id, ['accion' => 'Inventada']))->toThrow(QueryException::class);
});

it('una clave de operación solo se registra una vez por tenant', function () {
    $v = politicaPublicada('Permisos');
    accionDePoliticaDePrueba($v->politica_id, $v->id, ['operacion_id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'operacion_hash' => str_repeat('a', 64)]);

    expect(fn () => accionDePoliticaDePrueba($v->politica_id, $v->id, ['operacion_id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'operacion_hash' => str_repeat('b', 64)]))
        ->toThrow(QueryException::class);
});

it('la cabecera valida el tipo, es única por tenant y su contador no disminuye', function () {
    $c = cabeceraDePolitica('Permisos');
    DB::table('politicas_personal')->where('id', $c->id)->update(['ultimo_numero' => 3]);

    expect(fn () => DB::table('politicas_personal')->insert(['tenant_id' => 1, 'tipo' => 'Inventada', 'ultimo_numero' => 0, 'version' => 1]))->toThrow(QueryException::class)
        ->and(fn () => DB::table('politicas_personal')->insert(['tenant_id' => 1, 'tipo' => 'Permisos', 'ultimo_numero' => 0, 'version' => 1]))->toThrow(QueryException::class)
        ->and(fn () => DB::table('politicas_personal')->where('id', $c->id)->update(['ultimo_numero' => 2]))->toThrow(QueryException::class)
        ->and(fn () => DB::table('politicas_personal')->where('id', $c->id)->update(['tipo' => 'Licencias']))->toThrow(QueryException::class);
});

it('las llaves foráneas son restrictivas', function () {
    $v = politicaPublicada('Permisos');

    expect(fn () => DB::table('politicas_personal')->where('id', $v->politica_id)->delete())->toThrow(QueryException::class);
});
