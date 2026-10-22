<?php

use Illuminate\Support\Facades\DB;

it('300 versiones publicadas de un tipo: el detalle y la consulta aplicable responden completas y con consultas acotadas', function () {
    actuarComoAdminDeTenant();
    foreach (range(1, 300) as $i) {
        politicaPublicada('Permisos', ['inicio' => new DateTimeImmutable('-'.(400 - $i).' days', new DateTimeZone('UTC'))]);
    }

    $consultas = 0;
    DB::listen(function () use (&$consultas) {
        $consultas++;
    });

    $detalle = $this->getJson(urlPoliticas('permisos'))->assertOk();
    $consultasDetalle = $consultas;
    $consultas = 0;
    $aplicable = $this->getJson(urlPoliticas('permisos', '/aplicable'))->assertOk();

    $versiones = $detalle->json('data.versiones');
    expect($versiones)->toHaveCount(300)
        ->and(array_column($versiones, 'numero'))->toBe(range(300, 1))
        ->and($detalle->json('data.vigente.numero'))->toBe(300)
        ->and($aplicable->json('data.numero'))->toBe(300)
        ->and($consultasDetalle)->toBeLessThanOrEqual(8)
        ->and($consultas)->toBeLessThanOrEqual(8);
});

it('la consulta de la política aplicable usa el índice por política, inicio y número', function () {
    politicaPublicada('Permisos');
    $cabecera = cabeceraDePolitica('Permisos');

    $plan = DB::select('EXPLAIN QUERY PLAN select * from politica_versiones where politica_id = ? and publicada_en is not null and retirada_en is null and inicio_vigencia <= ? order by inicio_vigencia desc, numero desc', [$cabecera->id, '2030-01-01 00:00:00']);

    expect(collect($plan)->pluck('detail')->implode(' '))->toContain('politica_versiones_vigencia');
});
