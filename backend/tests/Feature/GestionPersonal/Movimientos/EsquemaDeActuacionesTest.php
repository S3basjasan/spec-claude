<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

function mensajeDeActuacion(callable $accion): string
{
    try {
        $accion();
    } catch (Throwable $e) {
        return $e->getMessage();
    }

    return '';
}

it('las columnas selladas no cambian nunca y la actuación no se elimina', function () {
    politicaVigente('Permisos');
    actuarComoAdminDeTenant();
    ['ficha' => $ficha] = fichaConVinculo();
    $id = (int) presentarNovedadApi($this, datosNovedad($ficha->id))->json('data.id');

    foreach (['politica_numero' => 7, 'politica_hash' => str_repeat('0', 64), 'motivo' => 'Otro', 'presentada_por' => 99, 'tenant_id' => 2, 'tipo' => 'Licencia'] as $columna => $valor) {
        expect(mensajeDeActuacion(fn () => DB::table('actuaciones_personal')->where('id', $id)->update([$columna => $valor])))->toContain('ACTUACION_INMUTABLE');
    }
    expect(mensajeDeActuacion(fn () => DB::table('actuaciones_personal')->where('id', $id)->delete()))->toContain('ACTUACION_NO_ELIMINABLE');

    // El estado y la versión sí cambian, por las operaciones de dominio.
    expect(DB::table('actuaciones_personal')->where('id', $id)->update(['estado' => 'EnRevision', 'version' => 2]))->toBe(1);
});

it('el registro de transiciones es append-only', function () {
    politicaVigente('Permisos');
    actuarComoAdminDeTenant();
    ['ficha' => $ficha] = fichaConVinculo();
    $id = (int) presentarNovedadApi($this, datosNovedad($ficha->id))->json('data.id');

    expect(mensajeDeActuacion(fn () => DB::table('actuacion_transiciones')->where('actuacion_id', $id)->update(['justificacion' => 'Editada'])))->toContain('TRANSICION_INMUTABLE')
        ->and(mensajeDeActuacion(fn () => DB::table('actuacion_transiciones')->where('actuacion_id', $id)->delete()))->toContain('TRANSICION_INMUTABLE');
});

it('los CHECK impiden tipos, categorías, estados y periodos incoherentes y un movimiento sin justificación', function () {
    politicaVigente('Permisos');
    politicaVigente('Ascensos');
    actuarComoAdminDeTenant();
    ['ficha' => $ficha, 'vinculo' => $vinculo] = fichaConVinculo();
    $version = DB::table('politica_versiones')->where('politica_id', cabeceraDePolitica('Permisos')->id)->first();
    $base = fn (array $c) => array_merge([
        'tenant_id' => 1, 'colaborador_id' => $ficha->id, 'vinculo_id' => $vinculo, 'tipo' => 'Permiso', 'categoria' => 'Novedad',
        'estado' => 'Presentada', 'motivo' => 'x', 'presentada_por' => 1, 'en_nombre_de_colaborador' => 0, 'presentada_en' => now(),
        'fecha_presentacion' => hoyDelTenant(), 'politica_version_id' => $version->id, 'politica_numero' => 1,
        'politica_condiciones' => '{}', 'politica_hash' => str_repeat('a', 64), 'version' => 1, 'operacion_id' => (string) Str::uuid(),
        'operacion_hash' => str_repeat('b', 64), 'created_by' => 1, 'updated_by' => 1, 'created_at' => now(), 'updated_at' => now(),
    ], $c);

    expect(mensajeDeActuacion(fn () => DB::table('actuaciones_personal')->insert($base(['tipo' => 'Descenso']))))->toContain('CHECK')
        ->and(mensajeDeActuacion(fn () => DB::table('actuaciones_personal')->insert($base(['categoria' => 'Movimiento']))))->toContain('CHECK')
        ->and(mensajeDeActuacion(fn () => DB::table('actuaciones_personal')->insert($base(['estado' => 'Programada']))))->toContain('CHECK')
        ->and(mensajeDeActuacion(fn () => DB::table('actuaciones_personal')->insert($base(['tipo' => 'Ausencia', 'estado' => 'Aprobada']))))->toContain('CHECK')
        ->and(mensajeDeActuacion(fn () => DB::table('actuaciones_personal')->insert($base(['tipo' => 'Ascenso', 'categoria' => 'Movimiento', 'motivo' => ' ']))))->toContain('MOVIMIENTO_SIN_JUSTIFICACION');

    $id = DB::table('actuaciones_personal')->insertGetId($base([]));
    expect(mensajeDeActuacion(fn () => DB::table('novedades_personal')->insert(['actuacion_id' => $id, 'tenant_id' => 1, 'fecha_inicio' => '2026-05-02', 'fecha_fin' => '2026-05-01'])))->toContain('CHECK');
});

it('la política conservada no puede eliminarse en cascada mientras una solicitud la use', function () {
    $version = politicaVigente('Permisos');
    actuarComoAdminDeTenant();
    ['ficha' => $ficha] = fichaConVinculo();
    presentarNovedadApi($this, datosNovedad($ficha->id))->assertCreated();

    expect(mensajeDeActuacion(fn () => DB::table('politica_versiones')->where('id', $version->id)->delete()))->not->toBe('');
    expect(DB::table('politica_versiones')->where('id', $version->id)->exists())->toBeTrue();
});

it('una actuación genera a lo sumo una asignación y las filas históricas conservan la referencia nula', function () {
    politicaVigente('Ascensos', ['anticipacion_dias' => 0]);
    actuarComoAdminDeTenant();
    $movimiento = movimientoAprobado($this, ['fecha_efectiva' => hoyDelTenant(0)]);
    accionApi($this, $movimiento['id'], 'aplicacion', ['version' => 2])->assertOk();

    // Otra persona, para no chocar con los triggers de superposición: solo el índice por actuación puede abortar.
    $otraFicha = fichaDe(['numero_identificacion' => 'ESQ1']);
    $otroVinculo = DB::table('vinculos_laborales')->insertGetId([
        'tenant_id' => 1, 'colaborador_id' => $otraFicha->id, 'fecha_ingreso' => hoyDelTenant(-10), 'fecha_salida' => null,
        'tipo_contrato_clave' => 'indefinido', 'version' => 1, 'operacion_id' => (string) Str::uuid(),
        'operacion_hash' => str_repeat('c', 64), 'created_by' => 1, 'updated_by' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);

    expect(Schema::hasColumn('asignaciones_laborales', 'actuacion_id'))->toBeTrue()
        ->and(DB::table('asignaciones_laborales')->whereNull('actuacion_id')->count())->toBe(1)
        ->and(mensajeDeActuacion(fn () => DB::table('asignaciones_laborales')->insert([
            'tenant_id' => 1, 'vinculo_id' => $otroVinculo, 'posicion_id' => posicionDeMovimiento()->id, 'fecha_inicio' => hoyDelTenant(1),
            'fecha_fin' => null, 'version' => 1, 'actuacion_id' => $movimiento['id'], 'created_by' => 1, 'updated_by' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ])))->toContain('asignaciones_laborales.actuacion_id');
});
