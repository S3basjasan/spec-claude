<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function novedadPresentada(mixed $prueba, int $colaboradorId, array $cambios = []): int
{
    return (int) presentarNovedadApi($prueba, datosNovedad($colaboradorId, $cambios))->assertCreated()->json('data.id');
}

it('pasa a revisión y luego aprueba conservando quién, cuándo, el sentido y la justificación (US2.2)', function () {
    politicaVigente('Permisos');
    $admin = actuarComoAdminDeTenant();
    ['ficha' => $ficha] = fichaConVinculo();
    $id = novedadPresentada($this, $ficha->id);

    $revision = accionApi($this, $id, 'revision', ['version' => 1])->assertOk();
    expect($revision->json('data.estado'))->toBe('EnRevision')->and($revision->json('data.version'))->toBe(2);

    $aprobada = accionApi($this, $id, 'aprobacion', ['version' => 2, 'justificacion' => 'Cumple con lo solicitado'])->assertOk();
    $historial = $aprobada->json('data.historial');

    expect($aprobada->json('data.estado'))->toBe('Aprobada')
        ->and(collect($historial)->pluck('evento')->all())->toBe(['Presentada', 'PasoARevision', 'Aprobada'])
        ->and($historial[2]['actor']['id'])->toBe($admin->id)
        ->and($historial[2]['justificacion'])->toBe('Cumple con lo solicitado')
        ->and($historial[2]['en'])->not->toBeNull();
});

it('rechaza con razón y el Colaborador ve el resultado comunicable (US2.3, RMN-CA08)', function () {
    politicaVigente('Permisos');
    actuarComoAdminDeTenant();
    ['ficha' => $ficha] = fichaConVinculo();
    $id = novedadPresentada($this, $ficha->id);

    accionApi($this, $id, 'rechazo', ['version' => 1])->assertStatus(422)->assertJsonPath('code', 'VALIDACION');
    accionApi($this, $id, 'rechazo', ['version' => 1, 'justificacion' => 'Sin cobertura en esas fechas'])->assertOk()->assertJsonPath('data.estado', 'Rechazada');

    actuarComoColaboradorDe($ficha);
    $propia = $this->getJson(URL_MIS_SOLICITUDES."/$id")->assertOk();

    expect($propia->json('data.estado'))->toBe('Rechazada')
        ->and($propia->json('data.resultado.sentido'))->toBe('Rechazada')
        ->and($propia->json('data.resultado.justificacion'))->toBe('Sin cobertura en esas fechas')
        ->and($propia->json())->not->toHaveKey('data.historial');
});

it('la solicitud conserva la versión de política con la que se presentó aunque entre otra antes de resolverla (US2.4, RMN-CA09)', function () {
    politicaVigente('Permisos', ['anticipacion_dias' => 2]);
    actuarComoAdminDeTenant();
    ['ficha' => $ficha] = fichaConVinculo();
    $id = novedadPresentada($this, $ficha->id);

    politicaPublicada('Permisos', ['numero' => 2, 'inicio' => new DateTimeImmutable('-1 hour'), 'parametros' => [...parametrosValidos('Permisos'), 'anticipacion_dias' => 9, 'disponible' => false]]);

    $aprobada = accionApi($this, $id, 'aprobacion', ['version' => 1, 'justificacion' => 'Aprobada con condiciones vigentes al presentar'])->assertOk();

    expect($aprobada->json('data.politica.numero'))->toBe(1)
        ->and($aprobada->json('data.politica.parametros.anticipacion_dias'))->toBe(2)
        ->and(DB::table('actuaciones_personal')->value('politica_numero'))->toBe(1);
});

it('señala el cruce de periodos y exige reconocerlo con constancia antes de aprobar; rechazar no lo exige (US2.2b)', function () {
    politicaVigente('Permisos');
    politicaVigente('Vacaciones', ['anticipacion_dias' => 0]);
    $admin = actuarComoAdminDeTenant();
    ['ficha' => $ficha] = fichaConVinculo();
    $vacaciones = (int) presentarNovedadApi($this, datosNovedad($ficha->id, ['tipo' => 'Vacaciones', 'fecha_inicio' => hoyDelTenant(5), 'fecha_fin' => hoyDelTenant(9)]))->json('data.id');
    $permiso = novedadPresentada($this, $ficha->id, ['fecha_inicio' => hoyDelTenant(8), 'fecha_fin' => hoyDelTenant(8)]);

    $detalle = $this->getJson(URL_MOVIMIENTOS."/$permiso")->assertOk();
    expect($detalle->json('data.cruces'))->toHaveCount(1)
        ->and($detalle->json('data.cruces.0.id'))->toBe($vacaciones)
        ->and(collect($detalle->json('data.comprobaciones'))->firstWhere('clave', 'cruce')['estado'])->toBe('Pendiente');

    accionApi($this, $permiso, 'aprobacion', ['version' => 1, 'justificacion' => 'Aprobado'])
        ->assertStatus(422)->assertJsonPath('code', 'CRUCE_SIN_RECONOCER');
    expect(DB::table('actuaciones_personal')->where('id', $permiso)->value('estado'))->toBe('Presentada');

    $aprobada = accionApi($this, $permiso, 'aprobacion', ['version' => 1, 'justificacion' => 'Aprobado', 'reconoce_cruce' => true])->assertOk();
    expect($aprobada->json('data.cruce_reconocido.por'))->toBe($admin->id)
        ->and($aprobada->json('data.cruce_reconocido.en'))->not->toBeNull()
        ->and(DB::table('novedades_personal')->where('actuacion_id', $permiso)->value('cruce_reconocido_por'))->toBe($admin->id);

    $otro = novedadPresentada($this, $ficha->id, ['fecha_inicio' => hoyDelTenant(7), 'fecha_fin' => hoyDelTenant(8)]);
    accionApi($this, $otro, 'rechazo', ['version' => 1, 'justificacion' => 'No procede'])->assertOk();
});

it('nada de lo resuelto se edita ni se elimina (US2.6)', function () {
    politicaVigente('Permisos');
    actuarComoAdminDeTenant();
    ['ficha' => $ficha] = fichaConVinculo();
    $id = novedadPresentada($this, $ficha->id);
    accionApi($this, $id, 'rechazo', ['version' => 1, 'justificacion' => 'No procede'])->assertOk();

    $this->patchJson(URL_MOVIMIENTOS."/$id", ['estado' => 'Aprobada'])->assertStatus(405);
    $this->putJson(URL_MOVIMIENTOS."/$id", ['estado' => 'Aprobada'])->assertStatus(405);
    $this->deleteJson(URL_MOVIMIENTOS."/$id")->assertStatus(405);
    accionApi($this, $id, 'aprobacion', ['version' => 2, 'justificacion' => 'Cambio de opinión'])
        ->assertStatus(409)->assertJsonPath('code', 'ESTADO_NO_RESOLUBLE');

    expect(fn () => DB::table('actuaciones_personal')->where('id', $id)->delete())->toThrow(Exception::class)
        ->and(fn () => DB::table('actuaciones_personal')->where('id', $id)->update(['politica_numero' => 99]))->toThrow(Exception::class)
        ->and(fn () => DB::table('actuacion_transiciones')->where('actuacion_id', $id)->delete())->toThrow(Exception::class);
});

it('si la resolución falla no queda a medias: conserva su estado y versión anteriores (US2.8)', function () {
    politicaVigente('Permisos');
    actuarComoAdminDeTenant();
    ['ficha' => $ficha] = fichaConVinculo();
    $id = novedadPresentada($this, $ficha->id);
    DB::unprepared("CREATE TRIGGER fallo_de_prueba BEFORE INSERT ON actuacion_transiciones WHEN NEW.evento = 'Aprobada' BEGIN SELECT RAISE(ABORT, 'FALLO_DE_PRUEBA'); END");

    accionApi($this, $id, 'aprobacion', ['version' => 1, 'justificacion' => 'Aprobada'])->assertStatus(500);
    DB::unprepared('DROP TRIGGER fallo_de_prueba');

    $fila = DB::table('actuaciones_personal')->where('id', $id)->first();
    expect($fila->estado)->toBe('Presentada')->and($fila->version)->toBe(1)
        ->and(DB::table('actuacion_transiciones')->where('actuacion_id', $id)->count())->toBe(1);

    accionApi($this, $id, 'aprobacion', ['version' => 1, 'justificacion' => 'Aprobada'])->assertOk();
});

it('una versión obsoleta o una transición inválida se rechazan sin cambiar nada', function () {
    politicaVigente('Permisos');
    actuarComoAdminDeTenant();
    ['ficha' => $ficha] = fichaConVinculo();
    $id = novedadPresentada($this, $ficha->id);
    accionApi($this, $id, 'revision', ['version' => 1])->assertOk();

    accionApi($this, $id, 'aprobacion', ['version' => 1, 'justificacion' => 'Con versión vieja'])
        ->assertStatus(409)->assertJsonPath('code', 'SOLICITUD_MODIFICADA');
    accionApi($this, $id, 'revision', ['version' => 2])
        ->assertStatus(409)->assertJsonPath('code', 'ESTADO_NO_RESOLUBLE');
    accionApi($this, $id, 'aprobacion', ['justificacion' => 'Sin versión'])
        ->assertStatus(422)->assertJsonPath('code', 'VALIDACION');

    expect(DB::table('actuaciones_personal')->value('estado'))->toBe('EnRevision');
});

it('una repetición idéntica de la aprobación no duplica estados ni eventos', function () {
    politicaVigente('Permisos');
    actuarComoAdminDeTenant();
    ['ficha' => $ficha] = fichaConVinculo();
    $id = novedadPresentada($this, $ficha->id);
    $clave = (string) Str::uuid();

    accionApi($this, $id, 'aprobacion', ['version' => 1, 'justificacion' => 'Aprobada'], $clave)->assertOk();
    accionApi($this, $id, 'aprobacion', ['version' => 1, 'justificacion' => 'Aprobada'], $clave)->assertOk();
    accionApi($this, $id, 'aprobacion', ['version' => 1, 'justificacion' => 'Otra razón'], $clave)
        ->assertStatus(409)->assertJsonPath('code', 'CLAVE_DE_OPERACION_REUTILIZADA');

    expect(DB::table('actuacion_transiciones')->where('actuacion_id', $id)->where('evento', 'Aprobada')->count())->toBe(1)
        ->and(DB::table('actuaciones_personal')->value('version'))->toBe(2);
});

it('dos aprobaciones simultáneas con la misma versión: solo una prospera (US2.7, RMN-CA12)', function () {
    politicaVigente('Permisos');
    actuarComoAdminDeTenant();
    ['ficha' => $ficha] = fichaConVinculo();
    $id = novedadPresentada($this, $ficha->id);

    $a = accionApi($this, $id, 'aprobacion', ['version' => 1, 'justificacion' => 'Primera']);
    $b = accionApi($this, $id, 'rechazo', ['version' => 1, 'justificacion' => 'Segunda']);

    expect([$a->status(), $b->status()])->toBe([200, 409])
        ->and($b->json('code'))->toBe('SOLICITUD_MODIFICADA')
        ->and(DB::table('actuacion_transiciones')->where('actuacion_id', $id)->whereIn('evento', ['Aprobada', 'Rechazada'])->count())->toBe(1);
});

it('una ausencia informada no se aprueba como si fuera un permiso (FR-026)', function () {
    politicaVigente('Ausencias', ['justificacion_obligatoria' => false]);
    actuarComoAdminDeTenant();
    ['ficha' => $ficha] = fichaConVinculo();
    $id = (int) informarAusenciaApi($this, ['colaborador_id' => $ficha->id, 'fecha_inicio' => hoyDelTenant(-2), 'fecha_fin' => hoyDelTenant(-1), 'motivo' => 'Inasistencia'])->assertCreated()->json('data.id');

    accionApi($this, $id, 'aprobacion', ['version' => 1, 'justificacion' => 'Aprobada'])->assertStatus(409)->assertJsonPath('code', 'ESTADO_NO_RESOLUBLE');
    accionApi($this, $id, 'revision', ['version' => 1])->assertStatus(409);
    expect(DB::table('actuaciones_personal')->value('estado'))->toBe('Informada');
});
