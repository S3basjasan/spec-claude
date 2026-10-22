<?php

use Illuminate\Support\Facades\DB;

/** Inserta en bloque adecuaciones de prueba (sin pasar por el repositorio) para medir el costo de las lecturas. */
function sembrarAdecuacionesEnBloque(int $cantidad): array
{
    $ids = range(1000, 1000 + $cantidad - 1);
    $ahora = now();
    foreach (array_chunk($ids, 100) as $bloque) {
        DB::table('adecuaciones')->insert(array_map(fn (int $id) => [
            'tenant_id' => 1, 'codigo' => sprintf('ADE-%04d', $id), 'colaborador_id' => $id, 'puesto_actual_id' => 1, 'puesto_objetivo_id' => 2,
            'inscripcion_id' => null, 'nivel_objetivo_id' => null, 'experiencia_requerida_anios' => null, 'preparacion' => 'EnDesarrollo',
            'umbral_meses' => 12, 'observaciones' => null, 'created_by' => 1, 'created_at' => $ahora, 'updated_at' => $ahora,
        ], $bloque));
    }
    $adecuacionIds = DB::table('adecuaciones')->orderBy('id')->pluck('id')->all();
    foreach (array_chunk($adecuacionIds, 100) as $bloque) {
        DB::table('adecuacion_detalles')->insert(array_merge(...array_map(fn (int $id) => [
            ['adecuacion_id' => $id, 'dimension' => 'Competencia', 'tipo_elemento' => 'Competencia', 'elemento_id' => 1, 'nombre_elemento' => 'Competencia 1',
                'requerido_actual_nivel' => 'C', 'requerido_objetivo_nivel' => 'B', 'valor_nivel' => 'C', 'diferencia' => -1, 'estado' => 'ConBrecha', 'tiempo_cierre_meses' => 6, 'created_at' => $ahora],
            ['adecuacion_id' => $id, 'dimension' => 'Competencia', 'tipo_elemento' => 'Competencia', 'elemento_id' => 2, 'nombre_elemento' => 'Competencia 2',
                'requerido_actual_nivel' => 'C', 'requerido_objetivo_nivel' => 'C', 'valor_nivel' => 'C', 'diferencia' => 0, 'estado' => 'Cumple', 'tiempo_cierre_meses' => null, 'created_at' => $ahora],
        ], $bloque)));
    }

    return $ids;
}

it('el mapa de 500 filas responde en menos de 3 segundos y con un número fijo de consultas', function () {
    actuarComoAdminDeTenant();
    $ids = sembrarAdecuacionesEnBloque(500);
    adecuacionFalsos(['vigentes' => array_fill_keys($ids, 'X'), 'unidades' => array_fill_keys($ids, 1)]);

    DB::enableQueryLog();
    $inicio = microtime(true);
    $r = $this->getJson('/api/adecuaciones/mapa?per_page=100')->assertOk()->json();
    $segundos = microtime(true) - $inicio;
    $consultas = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($r['meta']['total'])->toBe(500)->and($r['data'])->toHaveCount(100)
        ->and($segundos)->toBeLessThan(3.0)
        ->and($consultas)->toBeLessThan(20);
});

it('la comparación de 20 candidatos responde en menos de 3 segundos y sin consultas por candidato', function () {
    actuarComoAdminDeTenant();
    $ids = sembrarAdecuacionesEnBloque(40);
    adecuacionFalsos(['vigentes' => array_fill_keys($ids, 'X'), 'unidades' => array_fill_keys($ids, 1)]);
    $candidatos = implode('&', array_map(fn (int $id) => "colaborador_ids[]={$id}", array_slice($ids, 0, 20)));

    DB::enableQueryLog();
    $inicio = microtime(true);
    $r = $this->getJson("/api/adecuaciones/comparar?puesto_objetivo_id=2&{$candidatos}")->assertOk()->json('data');
    $segundos = microtime(true) - $inicio;
    $consultas = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($r['ranking'])->toHaveCount(20)->and($segundos)->toBeLessThan(3.0)->and($consultas)->toBeLessThan(15);
});

it('el historial paginado no ejecuta consultas por fila', function () {
    actuarComoAdminDeTenant();
    $ids = sembrarAdecuacionesEnBloque(60);
    adecuacionFalsos(['vigentes' => array_fill_keys($ids, 'X')]);

    DB::enableQueryLog();
    $this->getJson('/api/adecuaciones?per_page=50')->assertOk()->assertJsonCount(50, 'data');
    $consultas = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($consultas)->toBeLessThan(15);
});
