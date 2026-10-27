<?php

use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\DocumentoColaboradorModel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

afterEach(fn () => Carbon::setTestNow());

function tareaPorNombre(array $tareas, string $nombre): array
{
    return collect($tareas)->firstWhere('nombre', $nombre);
}

it('una tarea con evidencia obligatoria no se completa sin un respaldo válido y accesible (CA-04)', function () {
    politicaVigente('Offboarding');
    $salida = salidaFormalizada($this);
    $equipos = tareaPorNombre($salida['tareas'], 'Devolver equipos');

    accionSalidaApi($this, $salida['id'], "tareas/{$equipos['id']}/completar", ['version' => 1])->assertStatus(422)->assertJsonPath('code', 'EVIDENCIA_REQUERIDA');
    accionSalidaApi($this, $salida['id'], "tareas/{$equipos['id']}/completar", ['version' => 1, 'documento_ids' => [999999]])->assertStatus(422)->assertJsonPath('code', 'VALIDACION');
    $ajeno = respaldoDe(fichaDe(['numero_identificacion' => 'Z'.uniqid()]));
    accionSalidaApi($this, $salida['id'], "tareas/{$equipos['id']}/completar", ['version' => 1, 'documento_ids' => [$ajeno->id]])->assertStatus(422)->assertJsonPath('code', 'VALIDACION');
    expect(DB::table('offboarding_tareas')->where('id', $equipos['id'])->value('estado'))->toBe('Pendiente');

    $respaldo = respaldoDe($salida['ficha']);
    $hecha = accionSalidaApi($this, $salida['id'], "tareas/{$equipos['id']}/completar", ['version' => 1, 'documento_ids' => [$respaldo->id]])->assertOk();

    $tarea = tareaPorNombre($hecha->json('data.tareas'), 'Devolver equipos');
    expect($tarea['estado'])->toBe('Completada')
        ->and($tarea['completada_por']['id'])->not->toBeNull()
        ->and($tarea['respaldos'])->toBe([['id' => $respaldo->id, 'disponible' => true]])
        ->and($hecha->json('data.version'))->toBe(2);
});

it('una tarea sin evidencia obligatoria se completa sin respaldo y distingue pendientes de completadas (CA-04, CA-05)', function () {
    politicaVigente('Offboarding');
    $salida = salidaFormalizada($this);
    $accesos = tareaPorNombre($salida['tareas'], 'Cerrar accesos');

    $hecha = accionSalidaApi($this, $salida['id'], "tareas/{$accesos['id']}/completar", ['version' => 1])->assertOk();

    expect(collect($hecha->json('data.tareas'))->pluck('estado', 'nombre')->all())->toBe(['Devolver equipos' => 'Pendiente', 'Cerrar accesos' => 'Completada', 'Entrevista de salida' => 'Pendiente']);

    accionSalidaApi($this, $salida['id'], "tareas/{$accesos['id']}/completar", ['version' => 2])->assertStatus(409)->assertJsonPath('code', 'ESTADO_NO_PERMITIDO');
});

it('la tarea de entrevista no se completa a mano, una tarea ajena no existe y una versión vieja se rechaza', function () {
    politicaVigente('Offboarding');
    $salida = salidaFormalizada($this);
    $otra = salidaFormalizada($this);
    $entrevista = tareaPorNombre($salida['tareas'], 'Entrevista de salida');
    $accesos = tareaPorNombre($salida['tareas'], 'Cerrar accesos');

    accionSalidaApi($this, $salida['id'], "tareas/{$entrevista['id']}/completar", ['version' => 1])->assertStatus(409)->assertJsonPath('code', 'ESTADO_NO_PERMITIDO');
    accionSalidaApi($this, $otra['id'], "tareas/{$accesos['id']}/completar", ['version' => 1])->assertStatus(404)->assertJsonPath('code', 'EXPEDIENTE_NO_ENCONTRADO');
    accionSalidaApi($this, $salida['id'], "tareas/{$accesos['id']}/completar", ['version' => 7])->assertStatus(409)->assertJsonPath('code', 'EXPEDIENTE_MODIFICADO');
    accionSalidaApi($this, $salida['id'], "tareas/{$accesos['id']}/completar", ['version' => 1], 'no-es-uuid')->assertStatus(422)->assertJsonPath('code', 'CLAVE_DE_OPERACION_REQUERIDA');
});

it('completar la misma tarea con la misma clave devuelve el resultado anterior sin duplicar eventos', function () {
    politicaVigente('Offboarding');
    $salida = salidaFormalizada($this);
    $accesos = tareaPorNombre($salida['tareas'], 'Cerrar accesos');
    $clave = (string) Str::uuid();

    accionSalidaApi($this, $salida['id'], "tareas/{$accesos['id']}/completar", ['version' => 1], $clave)->assertOk();
    accionSalidaApi($this, $salida['id'], "tareas/{$accesos['id']}/completar", ['version' => 1], $clave)->assertOk();

    expect(DB::table('offboarding_eventos')->where('evento', 'TareaCompletada')->count())->toBe(1)
        ->and(DB::table('offboarding_expedientes')->value('version'))->toBe(2);
});

it('el cierre se rechaza mientras la salida no sea efectiva, aunque el checklist esté completo (CA-07, CA-08)', function () {
    politicaVigente('Offboarding');
    $salida = salidaFormalizada($this);
    completarChecklist($this, $salida);
    $version = $this->getJson(URL_OFFBOARDING.'/'.$salida['id'])->json('data.version');

    $rechazo = accionSalidaApi($this, $salida['id'], 'cierre', ['version' => $version])->assertStatus(422);

    expect($rechazo->json('code'))->toBe('CIERRE_NO_PROCEDE')
        ->and($rechazo->json('bloqueos'))->toContain('SALIDA_NO_EFECTIVA')
        ->and(DB::table('offboarding_expedientes')->value('cierre_estado'))->toBe('Pendiente');
});

it('el cierre exige tareas completas, entrevista resuelta y evidencia disponible; luego queda confirmado una sola vez', function () {
    politicaVigente('Offboarding');
    $salida = salidaFormalizada($this, ['fecha_efectiva' => hoyDelTenant(2)]);
    Carbon::setTestNow(now()->addDays(3));
    $this->artisan('offboarding:aplicar-salidas')->assertSuccessful();
    $version = fn () => $this->getJson(URL_OFFBOARDING.'/'.$salida['id'])->json('data.version');

    // Tareas pendientes.
    $pendientes = accionSalidaApi($this, $salida['id'], 'cierre', ['version' => $version()])->assertStatus(422);
    expect($pendientes->json('bloqueos'))->toEqualCanonicalizing(['TAREAS_PENDIENTES', 'ENTREVISTA_SIN_RESOLVER']);

    // Las tareas siguen disponibles después de la salida efectiva (CA-05) y se completan.
    completarChecklist($this, $salida);
    $sinEntrevista = accionSalidaApi($this, $salida['id'], 'cierre', ['version' => $version()])->assertStatus(422);
    expect($sinEntrevista->json('code'))->toBe('ENTREVISTA_SIN_RESOLVER');

    // El Administrador resuelve la tarea de entrevista como no realizada con motivo.
    $entrevista = $this->getJson(URL_OFFBOARDING.'/'.$salida['id'])->json('data.entrevista');
    accionSalidaApi($this, $salida['id'], 'entrevista/no-realizada', ['version' => $entrevista['version'], 'motivo' => 'SinRespuesta'])->assertOk();

    // Un respaldo que dejó de estar disponible impide el cierre.
    $respaldoId = DB::table('offboarding_documentos')->where('expediente_id', $salida['id'])->where('tarea_id', '<>', 0)->value('documento_id');
    DocumentoColaboradorModel::query()->whereKey($respaldoId)->update(['estado' => 'Archivado', 'archived_by' => 1, 'archived_at' => now()]);
    $sinEvidencia = accionSalidaApi($this, $salida['id'], 'cierre', ['version' => $version()])->assertStatus(422);
    expect($sinEvidencia->json('bloqueos'))->toBe(['EVIDENCIA_NO_DISPONIBLE']);
    DocumentoColaboradorModel::query()->whereKey($respaldoId)->update(['estado' => 'Disponible', 'archived_by' => null, 'archived_at' => null]);

    $clave = (string) Str::uuid();
    $versionDeCierre = $version();
    $cerrado = accionSalidaApi($this, $salida['id'], 'cierre', ['version' => $versionDeCierre], $clave)->assertOk();
    expect($cerrado->json('data.cierre.estado'))->toBe('Cerrado')
        ->and($cerrado->json('data.cierre.en'))->not->toBeNull()
        ->and($cerrado->json('data.estado'))->toBe('Efectiva')
        ->and($cerrado->json('data.acciones.cerrar'))->toBeFalse();

    accionSalidaApi($this, $salida['id'], 'cierre', ['version' => $versionDeCierre], $clave)->assertOk()->assertJsonPath('data.cierre.estado', 'Cerrado');
    accionSalidaApi($this, $salida['id'], 'cierre', ['version' => $cerrado->json('data.version')])->assertStatus(409)->assertJsonPath('code', 'ESTADO_NO_PERMITIDO');
    expect(DB::table('offboarding_eventos')->where('evento', 'CierreConfirmado')->count())->toBe(1);
});

it('el cierre no altera el vínculo ni la salida: la vigencia sigue la fecha efectiva (CA-08)', function () {
    politicaVigente('Offboarding');
    $salida = salidaFormalizada($this, ['fecha_efectiva' => hoyDelTenant(2)]);
    Carbon::setTestNow(now()->addDays(3));
    $this->artisan('offboarding:aplicar-salidas')->assertSuccessful();
    $vinculoAntes = (array) DB::table('vinculos_laborales')->where('id', $salida['vinculo'])->first();

    $detalle = $this->getJson(URL_OFFBOARDING.'/'.$salida['id'])->assertOk();

    expect($detalle->json('data.estado'))->toBe('Efectiva')
        ->and($detalle->json('data.cierre.estado'))->toBe('Pendiente')
        ->and($vinculoAntes['fecha_salida'])->toBe(hoyDelTenant(-1))
        ->and(DB::table('offboarding_tareas')->where('expediente_id', $salida['id'])->where('estado', 'Pendiente')->count())->toBe(3);
});

it('una salida cancelada no admite tareas, entrevista ni cierre', function () {
    politicaVigente('Offboarding');
    $salida = salidaFormalizada($this);
    $accesos = tareaPorNombre($salida['tareas'], 'Cerrar accesos');
    accionSalidaApi($this, $salida['id'], 'cancelacion', ['version' => 1, 'razon' => 'Cancelada'])->assertOk();

    accionSalidaApi($this, $salida['id'], "tareas/{$accesos['id']}/completar", ['version' => 2])->assertStatus(409)->assertJsonPath('code', 'ESTADO_NO_PERMITIDO');
    accionSalidaApi($this, $salida['id'], 'entrevista/no-realizada', ['version' => 1, 'motivo' => 'Declinada'])->assertStatus(409)->assertJsonPath('code', 'ESTADO_NO_PERMITIDO');
    accionSalidaApi($this, $salida['id'], 'cierre', ['version' => 2])->assertStatus(422)->assertJsonPath('code', 'CIERRE_NO_PROCEDE');
});

it('listado, resumen y detalle quedan aislados por tenant y exigen Administrador (RN aislamiento)', function () {
    politicaVigente('Offboarding');
    politicaVigente('Offboarding', [], 2);
    $propia = salidaFormalizada($this);
    salidaFormalizada($this, [], null, 2);
    actuarComoAdminDeTenant();

    $lista = $this->getJson(URL_OFFBOARDING)->assertOk();
    expect($lista->json('data'))->toHaveCount(1)
        ->and($lista->json('data.0.id'))->toBe($propia['id'])
        ->and($lista->json('meta.total'))->toBe(1);
    expect($this->getJson(URL_OFFBOARDING.'/resumen')->json('data.programadas'))->toBe(1);

    $ajena = (int) DB::table('offboarding_expedientes')->where('tenant_id', 2)->value('id');
    foreach (['', '/entrevista/respuestas', '/entrevista/estructura'] as $sufijo) {
        $this->getJson(URL_OFFBOARDING."/{$ajena}{$sufijo}")->assertStatus($sufijo === '' ? 404 : 403);
    }
    accionSalidaApi($this, $ajena, 'cancelacion', ['version' => 1, 'razon' => 'x'])->assertStatus(404)->assertJsonPath('code', 'EXPEDIENTE_NO_ENCONTRADO');
    accionSalidaApi($this, $ajena, 'aplicacion', ['version' => 1])->assertStatus(404);
    $this->getJson(URL_OFFBOARDING.'/999999')->assertStatus(404)->assertJsonPath('code', 'EXPEDIENTE_NO_ENCONTRADO');

    actuarComoUsuarioDeTenant();
    $this->getJson(URL_OFFBOARDING)->assertForbidden();
    $this->postJson(URL_OFFBOARDING, [])->assertForbidden();
});

it('el listado filtra por estado, motivo, entrevista y cierre y valida los filtros', function () {
    politicaVigente('Offboarding');
    salidaFormalizada($this, ['motivo' => 'Jubilacion']);
    salidaFormalizada($this, ['motivo' => 'Renuncia']);

    expect($this->getJson(URL_OFFBOARDING.'?motivo=Jubilacion')->json('data'))->toHaveCount(1)
        ->and($this->getJson(URL_OFFBOARDING.'?estado=Programada&cierre=Pendiente&entrevista=Pendiente')->json('data'))->toHaveCount(2)
        ->and($this->getJson(URL_OFFBOARDING.'?estado=Efectiva')->json('data'))->toHaveCount(0)
        ->and($this->getJson(URL_OFFBOARDING.'?per_page=1')->json('meta.last_page'))->toBe(2);
    $this->getJson(URL_OFFBOARDING.'?estado=Inventado')->assertStatus(422);
    $this->getJson(URL_OFFBOARDING.'?tenant_id=2')->assertStatus(422)->assertJsonPath('code', 'CAMPO_NO_PERMITIDO');
});

it('no existen rutas para editar ni eliminar un expediente', function () {
    politicaVigente('Offboarding');
    $salida = salidaFormalizada($this);

    $this->putJson(URL_OFFBOARDING.'/'.$salida['id'], [])->assertStatus(405);
    $this->patchJson(URL_OFFBOARDING.'/'.$salida['id'], [])->assertStatus(405);
    $this->deleteJson(URL_OFFBOARDING.'/'.$salida['id'])->assertStatus(405);
});
