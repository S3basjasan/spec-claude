<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function mensajeDeSalida(callable $accion): string
{
    try {
        $accion();
    } catch (Throwable $e) {
        return $e->getMessage();
    }

    return '';
}

it('las columnas selladas del expediente no cambian nunca y el expediente no se elimina', function () {
    politicaVigente('Offboarding');
    $salida = salidaFormalizada($this);

    foreach (['motivo' => 'Jubilacion', 'fecha_efectiva' => hoyDelTenant(99), 'politica_numero' => 7, 'politica_hash' => str_repeat('0', 64), 'formalizado_por' => 99, 'tenant_id' => 2, 'colaborador_id' => 99] as $columna => $valor) {
        expect(mensajeDeSalida(fn () => DB::table('offboarding_expedientes')->where('id', $salida['id'])->update([$columna => $valor])))->toContain('EXPEDIENTE_INMUTABLE');
    }
    expect(mensajeDeSalida(fn () => DB::table('offboarding_expedientes')->where('id', $salida['id'])->delete()))->toContain('EXPEDIENTE_NO_ELIMINABLE');

    // El estado, el cierre y la versión sí cambian, por las operaciones de dominio.
    expect(DB::table('offboarding_expedientes')->where('id', $salida['id'])->update(['version' => 2, 'cuenta_estado' => 'Coordinada']))->toBe(1);
});

it('una salida efectiva o cancelada es terminal', function () {
    politicaVigente('Offboarding');
    $salida = salidaFormalizada($this);
    accionSalidaApi($this, $salida['id'], 'cancelacion', ['version' => 1, 'razon' => 'Cancelada'])->assertOk();

    expect(mensajeDeSalida(fn () => DB::table('offboarding_expedientes')->where('id', $salida['id'])->update(['estado' => 'Programada', 'cancelacion_razon' => null])))->toContain('EXPEDIENTE_TERMINAL');
});

it('los CHECK impiden motivos, estados e incoherencias de cierre, cancelación e incidencia', function () {
    politicaVigente('Offboarding');
    $salida = salidaFormalizada($this);
    $id = $salida['id'];
    $actualizar = fn (array $cambios) => mensajeDeSalida(fn () => DB::table('offboarding_expedientes')->where('id', $id)->update($cambios));

    expect($actualizar(['estado' => 'Inventado']))->toContain('CHECK')
        ->and($actualizar(['cierre_estado' => 'Cerrado']))->toContain('CHECK')
        ->and($actualizar(['cuenta_estado' => 'Deshabilitada']))->toContain('CHECK')
        ->and($actualizar(['estado' => 'Cancelada']))->toContain('CHECK')
        ->and($actualizar(['estado' => 'IncidenciaDeAplicacion']))->toContain('CHECK')
        ->and($actualizar(['incidencia_codigo' => 'X']))->toContain('CHECK');
});

it('solo hay una salida viva por vínculo a nivel de base de datos, pero se admiten varias canceladas', function () {
    politicaVigente('Offboarding');
    $salida = salidaFormalizada($this);
    $duplicada = fn () => DB::table('offboarding_expedientes')->insert(
        array_replace((array) DB::table('offboarding_expedientes')->where('id', $salida['id'])->first(), ['id' => null, 'operacion_id' => (string) Str::uuid()]),
    );

    expect(mensajeDeSalida($duplicada))->toContain('UNIQUE');

    accionSalidaApi($this, $salida['id'], 'cancelacion', ['version' => 1, 'razon' => 'Cancelada'])->assertOk();
    $duplicada();
    expect(DB::table('offboarding_expedientes')->count())->toBe(2);
});

it('la clave de operación es única por tenant en expedientes y en eventos', function () {
    politicaVigente('Offboarding');
    $salida = salidaFormalizada($this);
    $evento = (array) DB::table('offboarding_eventos')->where('expediente_id', $salida['id'])->first();

    expect(mensajeDeSalida(fn () => DB::table('offboarding_eventos')->insert(array_replace($evento, ['id' => null]))))->toContain('UNIQUE');
});

it('el registro de eventos es append-only', function () {
    politicaVigente('Offboarding');
    $salida = salidaFormalizada($this);

    expect(mensajeDeSalida(fn () => DB::table('offboarding_eventos')->where('expediente_id', $salida['id'])->update(['evento' => 'Cancelada'])))->toContain('EVENTO_INMUTABLE')
        ->and(mensajeDeSalida(fn () => DB::table('offboarding_eventos')->where('expediente_id', $salida['id'])->delete()))->toContain('EVENTO_INMUTABLE');
});

it('las tareas conservan su plantilla: no cambian de nombre, orden ni tipo, no se eliminan y solo se completan', function () {
    politicaVigente('Offboarding');
    $salida = salidaFormalizada($this);
    $tarea = $salida['tareas'][0]['id'];

    foreach (['nombre' => 'Otro', 'orden' => 9, 'tipo' => 'Entrevista', 'exige_evidencia' => 0, 'descripcion' => 'x'] as $columna => $valor) {
        expect(mensajeDeSalida(fn () => DB::table('offboarding_tareas')->where('id', $tarea)->update([$columna => $valor])))->toContain('TAREA_INMUTABLE');
    }
    expect(mensajeDeSalida(fn () => DB::table('offboarding_tareas')->where('id', $tarea)->delete()))->toContain('TAREA_NO_ELIMINABLE');
});

it('una respuesta fuera de 1–5, un texto en una valoración y una pregunta repetida se rechazan', function () {
    politicaVigente('Offboarding');
    $salida = salidaFormalizada($this);
    $entrevista = (int) DB::table('offboarding_entrevistas')->where('expediente_id', $salida['id'])->value('id');
    $fila = fn (array $cambios) => array_replace([
        'tenant_id' => 1, 'entrevista_id' => $entrevista, 'cuestionario_version' => 1, 'pregunta_id' => 'DIM-GENERAL', 'tipo' => 'valoracion',
        'dimension_o_categoria' => 'Experiencia general', 'valor' => 3, 'texto' => null, 'created_at' => now(),
    ], $cambios);

    expect(mensajeDeSalida(fn () => DB::table('offboarding_respuestas')->insert($fila(['valor' => 0]))))->toContain('CHECK')
        ->and(mensajeDeSalida(fn () => DB::table('offboarding_respuestas')->insert($fila(['valor' => 6]))))->toContain('CHECK')
        ->and(mensajeDeSalida(fn () => DB::table('offboarding_respuestas')->insert($fila(['valor' => null]))))->toContain('CHECK')
        ->and(mensajeDeSalida(fn () => DB::table('offboarding_respuestas')->insert($fila(['tipo' => 'abierta', 'texto' => null, 'valor' => null]))))->toContain('CHECK');

    DB::table('offboarding_respuestas')->insert($fila([]));
    expect(mensajeDeSalida(fn () => DB::table('offboarding_respuestas')->insert($fila(['valor' => 4]))))->toContain('UNIQUE');
});

it('una entrevista cerrada no cambia, no se elimina y no admite respuestas nuevas', function () {
    politicaVigente('Offboarding');
    $salida = salidaFormalizada($this);
    $version = $this->getJson(URL_OFFBOARDING.'/'.$salida['id'])->json('data.entrevista.version');
    accionSalidaApi($this, $salida['id'], 'entrevista/no-realizada', ['version' => $version, 'motivo' => 'Declinada'])->assertOk();
    $entrevista = (int) DB::table('offboarding_entrevistas')->where('expediente_id', $salida['id'])->value('id');

    expect(mensajeDeSalida(fn () => DB::table('offboarding_entrevistas')->where('id', $entrevista)->update(['estado' => 'Pendiente', 'motivo_no_realizada' => null])))->toContain('ENTREVISTA_CERRADA')
        ->and(mensajeDeSalida(fn () => DB::table('offboarding_entrevistas')->where('id', $entrevista)->delete()))->toContain('ENTREVISTA_NO_ELIMINABLE')
        ->and(mensajeDeSalida(fn () => DB::table('offboarding_respuestas')->insert([
            'tenant_id' => 1, 'entrevista_id' => $entrevista, 'cuestionario_version' => 1, 'pregunta_id' => 'DIM-GENERAL', 'tipo' => 'valoracion',
            'dimension_o_categoria' => 'Experiencia general', 'valor' => 3, 'texto' => null, 'created_at' => now(),
        ])))->toContain('ENTREVISTA_CERRADA');
});

it('no se puede eliminar una política publicada referenciada por un expediente', function () {
    $politica = politicaVigente('Offboarding');
    salidaFormalizada($this);

    expect(mensajeDeSalida(fn () => DB::table('politica_versiones')->where('id', $politica->id)->delete()))->not->toBe('');
});
