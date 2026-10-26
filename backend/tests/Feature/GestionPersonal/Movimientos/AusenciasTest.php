<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('informa una ausencia ya ocurrida como hecho informado, sin declararla permiso aprobado (US3.1, RMN-CA07)', function () {
    politicaVigente('Ausencias', ['justificacion_obligatoria' => false]);
    actuarComoAdminDeTenant();
    ['ficha' => $ficha] = fichaConVinculo();

    $respuesta = informarAusenciaApi($this, [
        'colaborador_id' => $ficha->id, 'fecha_inicio' => hoyDelTenant(-3), 'fecha_fin' => hoyDelTenant(-2), 'motivo' => 'Inasistencia sin aviso',
    ])->assertCreated();

    expect($respuesta->json('data.estado'))->toBe('Informada')
        ->and($respuesta->json('data.tipo'))->toBe('Ausencia')
        ->and($respuesta->json('data.periodo.ocurrida'))->toBeTrue()
        ->and($respuesta->json('data.acciones.aprobar'))->toBeFalse()
        ->and($respuesta->json('data.acciones.revisar_ausencia'))->toBeTrue()
        ->and(DB::table('actuaciones_personal')->where('estado', 'Aprobada')->count())->toBe(0);
});

it('no aplica la anticipación mínima ni inventa retroactividad, pero no admite fechas futuras (US3.3, FR-012)', function () {
    politicaVigente('Ausencias', ['justificacion_obligatoria' => false]);
    politicaVigente('Permisos', ['anticipacion_dias' => 30]);
    actuarComoAdminDeTenant();
    ['ficha' => $ficha] = fichaConVinculo();
    $cuerpo = ['colaborador_id' => $ficha->id, 'fecha_inicio' => hoyDelTenant(0), 'fecha_fin' => hoyDelTenant(0)];

    informarAusenciaApi($this, $cuerpo)->assertCreated();
    informarAusenciaApi($this, [...$cuerpo, 'fecha_inicio' => hoyDelTenant(-400), 'fecha_fin' => hoyDelTenant(-399)])->assertCreated();
    informarAusenciaApi($this, [...$cuerpo, 'fecha_fin' => hoyDelTenant(1)])
        ->assertStatus(422)->assertJsonPath('code', 'AUSENCIA_FUTURA');
    informarAusenciaApi($this, [...$cuerpo, 'fecha_inicio' => hoyDelTenant(0), 'fecha_fin' => hoyDelTenant(-1)])
        ->assertStatus(422)->assertJsonPath('code', 'VALIDACION');
});

it('exige la justificación y el respaldo que pide la política de ausencias y su disponibilidad', function () {
    actuarComoAdminDeTenant();
    ['ficha' => $ficha] = fichaConVinculo();
    $cuerpo = ['colaborador_id' => $ficha->id, 'fecha_inicio' => hoyDelTenant(-1), 'fecha_fin' => hoyDelTenant(-1), 'motivo' => ''];

    informarAusenciaApi($this, $cuerpo)->assertStatus(422)->assertJsonPath('code', 'POLITICA_SIN_CONFIGURAR');

    politicaVigente('Ausencias', ['justificacion_obligatoria' => true, 'documento_obligatorio' => true]);
    informarAusenciaApi($this, $cuerpo)->assertStatus(422)->assertJsonPath('code', 'JUSTIFICACION_REQUERIDA');
    informarAusenciaApi($this, [...$cuerpo, 'motivo' => 'Enfermedad'])->assertStatus(422)->assertJsonPath('code', 'DOCUMENTO_REQUERIDO');
    informarAusenciaApi($this, [...$cuerpo, 'motivo' => 'Enfermedad', 'documento_ids' => [respaldoDe($ficha)->id]])->assertCreated();

    politicaVigente('Ausencias', ['disponible' => false]);
    informarAusenciaApi($this, $cuerpo)->assertStatus(422)->assertJsonPath('code', 'POLITICA_NO_ADMITE_SOLICITUDES');
});

it('revisa o justifica una ausencia con actor, momento y observación, distinguiendo ambos estados (US3.2)', function () {
    politicaVigente('Ausencias', ['justificacion_obligatoria' => false]);
    $admin = actuarComoAdminDeTenant();
    ['ficha' => $ficha] = fichaConVinculo();
    $crear = fn () => (int) informarAusenciaApi($this, ['colaborador_id' => $ficha->id, 'fecha_inicio' => hoyDelTenant(-1), 'fecha_fin' => hoyDelTenant(-1)])->json('data.id');
    $uno = $crear();
    $dos = $crear();

    accionApi($this, $uno, 'revision-de-ausencia', ['version' => 1, 'resultado' => 'Revisada'])->assertStatus(422);
    accionApi($this, $uno, 'revision-de-ausencia', ['version' => 1, 'resultado' => 'Aprobada', 'observacion' => 'x'])->assertStatus(422);
    $revisada = accionApi($this, $uno, 'revision-de-ausencia', ['version' => 1, 'resultado' => 'Revisada', 'observacion' => 'Verificada con el jefe'])->assertOk();
    $justificada = accionApi($this, $dos, 'revision-de-ausencia', ['version' => 1, 'resultado' => 'Justificada', 'observacion' => 'Certificado médico'])->assertOk();

    expect($revisada->json('data.estado'))->toBe('Revisada')
        ->and($justificada->json('data.estado'))->toBe('Justificada')
        ->and($justificada->json('data.revision.observacion'))->toBe('Certificado médico')
        ->and($justificada->json('data.historial.1.actor.id'))->toBe($admin->id)
        ->and($justificada->json('data.historial.1.justificacion'))->toBe('Certificado médico');

    accionApi($this, $uno, 'revision-de-ausencia', ['version' => 2, 'resultado' => 'Justificada', 'observacion' => 'Otra vez'])
        ->assertStatus(409)->assertJsonPath('code', 'ESTADO_NO_RESOLUBLE');
});

it('solo una ausencia puede revisarse con esa acción', function () {
    politicaVigente('Permisos');
    actuarComoAdminDeTenant();
    ['ficha' => $ficha] = fichaConVinculo();
    $permiso = (int) presentarNovedadApi($this, datosNovedad($ficha->id))->json('data.id');

    accionApi($this, $permiso, 'revision-de-ausencia', ['version' => 1, 'resultado' => 'Revisada', 'observacion' => 'x'])
        ->assertStatus(409)->assertJsonPath('code', 'ESTADO_NO_RESOLUBLE');
});

it('el Colaborador no registra ausencias y solo ve las propias una vez revisadas o justificadas (US3.4)', function () {
    politicaVigente('Ausencias', ['justificacion_obligatoria' => false]);
    actuarComoAdminDeTenant();
    ['ficha' => $ficha] = fichaConVinculo();
    $id = (int) informarAusenciaApi($this, ['colaborador_id' => $ficha->id, 'fecha_inicio' => hoyDelTenant(-1), 'fecha_fin' => hoyDelTenant(-1)])->json('data.id');

    actuarComoColaboradorDe($ficha);
    $this->postJson(URL_MOVIMIENTOS.'/ausencias', ['colaborador_id' => $ficha->id], ['Idempotency-Key' => (string) Str::uuid()])->assertForbidden();
    expect($this->getJson(URL_MIS_SOLICITUDES)->json('data'))->toBe([]);
    $this->getJson(URL_MIS_SOLICITUDES."/$id")->assertNotFound();

    actuarComoAdminDeTenant();
    accionApi($this, $id, 'revision-de-ausencia', ['version' => 1, 'resultado' => 'Justificada', 'observacion' => 'Certificado'])->assertOk();

    actuarComoColaboradorDe($ficha);
    $propia = $this->getJson(URL_MIS_SOLICITUDES."/$id")->assertOk();
    expect($propia->json('data.estado'))->toBe('Justificada')
        ->and($propia->json('data.resultado.justificacion'))->toBe('Certificado')
        ->and($this->getJson(URL_MIS_SOLICITUDES)->json('data'))->toHaveCount(1);
});
