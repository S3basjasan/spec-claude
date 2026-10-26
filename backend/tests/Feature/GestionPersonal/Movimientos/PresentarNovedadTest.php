<?php

use App\Modules\GestionPersonal\Application\Services\PoliticasService;
use App\Modules\GestionPersonal\Domain\Exceptions\PoliticaNoDisponible;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('presenta un permiso con la versión de política y la copia de sus condiciones, sin tocar la asignación (US1.1, RMN-CA05)', function () {
    $politica = politicaVigente('Permisos', ['justificacion_obligatoria' => true, 'anticipacion_dias' => 2]);
    $admin = actuarComoAdminDeTenant();
    ['ficha' => $ficha] = fichaConVinculo();
    $antes = [DB::table('vinculos_laborales')->count(), DB::table('asignaciones_laborales')->get()->toArray()];

    $respuesta = presentarNovedadApi($this, datosNovedad($ficha->id))->assertCreated();

    expect($respuesta->json('data.estado'))->toBe('Presentada')
        ->and($respuesta->json('data.tipo'))->toBe('Permiso')
        ->and($respuesta->json('data.politica.numero'))->toBe(1)
        ->and($respuesta->json('data.politica.version_id'))->toBe($politica->id)
        ->and($respuesta->json('data.politica.parametros.anticipacion_dias'))->toBe(2)
        ->and($respuesta->json('data.presentada.por.id'))->toBe($admin->id)
        ->and($respuesta->json('data.periodo.inicio'))->toBe(hoyDelTenant(5));

    $fila = DB::table('actuaciones_personal')->first();
    expect($fila->politica_version_id)->toBe($politica->id)
        ->and(json_decode($fila->politica_condiciones, true)['parametros']['anticipacion_dias'])->toBe(2)
        ->and([DB::table('vinculos_laborales')->count(), DB::table('asignaciones_laborales')->get()->toArray()])->toEqual($antes);
});

it('distingue política sin configurar, sin solicitudes nuevas y falla al consultarla (US1.4, RMN-CA10)', function () {
    actuarComoAdminDeTenant();
    ['ficha' => $ficha] = fichaConVinculo();

    presentarNovedadApi($this, datosNovedad($ficha->id))
        ->assertStatus(422)->assertJsonPath('code', 'POLITICA_SIN_CONFIGURAR');

    politicaVigente('Permisos', ['disponible' => false]);
    presentarNovedadApi($this, datosNovedad($ficha->id))
        ->assertStatus(422)->assertJsonPath('code', 'POLITICA_NO_ADMITE_SOLICITUDES');

    $this->mock(PoliticasService::class, fn ($m) => $m->shouldReceive('seleccionarParaPresentar')->andThrow(new PoliticaNoDisponible));
    presentarNovedadApi($this, datosNovedad($ficha->id))
        ->assertStatus(503)->assertJsonPath('code', 'POLITICA_NO_DISPONIBLE');

    expect(DB::table('actuaciones_personal')->count())->toBe(0);
});

it('mide la anticipación en días calendario de la zona del tenant (US1.2, RMN-CA06)', function () {
    politicaVigente('Vacaciones', ['anticipacion_dias' => 10, 'justificacion_obligatoria' => false]);
    actuarComoAdminDeTenant();
    ['ficha' => $ficha] = fichaConVinculo();

    $corto = datosNovedad($ficha->id, ['tipo' => 'Vacaciones', 'fecha_inicio' => hoyDelTenant(3), 'fecha_fin' => hoyDelTenant(8)]);
    presentarNovedadApi($this, $corto)
        ->assertStatus(422)->assertJsonPath('code', 'ANTICIPACION_INSUFICIENTE');
    expect(DB::table('actuaciones_personal')->count())->toBe(0);

    presentarNovedadApi($this, datosNovedad($ficha->id, ['tipo' => 'Vacaciones', 'fecha_inicio' => hoyDelTenant(10), 'fecha_fin' => hoyDelTenant(15)]))
        ->assertCreated();
});

it('con anticipación cero admite el día actual pero nunca una fecha pasada (US1.3)', function () {
    politicaVigente('Permisos', ['anticipacion_dias' => 0, 'justificacion_obligatoria' => false]);
    actuarComoAdminDeTenant();
    ['ficha' => $ficha] = fichaConVinculo();

    presentarNovedadApi($this, datosNovedad($ficha->id, ['fecha_inicio' => hoyDelTenant(0), 'fecha_fin' => hoyDelTenant(0)]))->assertCreated();
    presentarNovedadApi($this, datosNovedad($ficha->id, ['fecha_inicio' => hoyDelTenant(-1), 'fecha_fin' => hoyDelTenant(0)]))
        ->assertStatus(422)->assertJsonPath('code', 'FECHA_PASADA');
});

it('exige la justificación y el respaldo que pide la política y no presume un respaldo ajeno (US1.5)', function () {
    politicaVigente('Licencias', ['justificacion_obligatoria' => true, 'documento_obligatorio' => true, 'anticipacion_dias' => 0]);
    actuarComoAdminDeTenant();
    ['ficha' => $ficha] = fichaConVinculo();
    ['ficha' => $otra] = fichaConVinculo();
    $cuerpo = datosNovedad($ficha->id, ['tipo' => 'Licencia']);

    presentarNovedadApi($this, [...$cuerpo, 'motivo' => ''])
        ->assertStatus(422)->assertJsonPath('code', 'JUSTIFICACION_REQUERIDA');
    presentarNovedadApi($this, $cuerpo)
        ->assertStatus(422)->assertJsonPath('code', 'DOCUMENTO_REQUERIDO');
    presentarNovedadApi($this, [...$cuerpo, 'documento_ids' => [respaldoDe($otra)->id]])
        ->assertStatus(422)->assertJsonPath('code', 'VALIDACION')->assertJsonValidationErrorFor('documento_ids');
    presentarNovedadApi($this, [...$cuerpo, 'documento_ids' => [respaldoDe($ficha, ['estado' => 'Archivado', 'archived_by' => 1, 'archived_at' => now()])->id]])
        ->assertStatus(422)->assertJsonPath('code', 'VALIDACION');

    $respuesta = presentarNovedadApi($this, [...$cuerpo, 'documento_ids' => [respaldoDe($ficha)->id]])->assertCreated();
    expect($respuesta->json('data.documentos'))->toHaveCount(1)
        ->and($respuesta->json('data.documentos.0.disponible'))->toBeTrue();
});

it('el Administrador que registra por el colaborador conserva ambas identidades (US1.6)', function () {
    politicaVigente('Permisos');
    $admin = actuarComoAdminDeTenant();
    ['ficha' => $ficha] = fichaConVinculo();

    $respuesta = presentarNovedadApi($this, datosNovedad($ficha->id))->assertCreated();

    expect($respuesta->json('data.colaborador.id'))->toBe($ficha->id)
        ->and($respuesta->json('data.presentada.por.id'))->toBe($admin->id)
        ->and($respuesta->json('data.presentada.en_nombre_de_colaborador'))->toBeTrue();
});

it('un borrador de formulario no reserva versión: se aplica la vigente al presentar (US1.7)', function () {
    politicaVigente('Permisos', ['anticipacion_dias' => 2]);
    actuarComoAdminDeTenant();
    ['ficha' => $ficha] = fichaConVinculo();
    $this->getJson('/api/politicas-personal/permisos/aplicable')->assertOk()->assertJsonPath('data.numero', 1);

    politicaPublicada('Permisos', ['numero' => 2, 'inicio' => new DateTimeImmutable('-1 hour'), 'parametros' => [...parametrosValidos('Permisos'), 'anticipacion_dias' => 4]]);

    $respuesta = presentarNovedadApi($this, datosNovedad($ficha->id, ['fecha_inicio' => hoyDelTenant(2)]));

    $respuesta->assertStatus(422)->assertJsonPath('code', 'ANTICIPACION_INSUFICIENTE');
    presentarNovedadApi($this, datosNovedad($ficha->id, ['fecha_inicio' => hoyDelTenant(4), 'fecha_fin' => hoyDelTenant(4)]))
        ->assertCreated()->assertJsonPath('data.politica.numero', 2);
});

it('rechaza al colaborador sin vínculo vigente y la ficha de otro tenant (edge cases)', function () {
    politicaVigente('Permisos');
    actuarComoAdminDeTenant();
    $sinVinculo = fichaDe(['numero_identificacion' => 'SV1']);
    ['ficha' => $ajena] = fichaConVinculo([], 2);

    presentarNovedadApi($this, datosNovedad($sinVinculo->id))
        ->assertStatus(422)->assertJsonPath('code', 'SIN_VINCULO_VIGENTE');
    presentarNovedadApi($this, datosNovedad($ajena->id))
        ->assertNotFound()->assertJsonPath('code', 'FICHA_NO_ENCONTRADA');
});

it('no acepta campos que el cliente no debe fijar ni un tipo que no sea una novedad solicitada', function () {
    politicaVigente('Permisos');
    actuarComoAdminDeTenant();
    ['ficha' => $ficha] = fichaConVinculo();

    presentarNovedadApi($this, [...datosNovedad($ficha->id), 'tenant_id' => 2])
        ->assertStatus(422)->assertJsonPath('code', 'CAMPO_NO_PERMITIDO');
    presentarNovedadApi($this, [...datosNovedad($ficha->id), 'estado' => 'Aprobada'])
        ->assertStatus(422)->assertJsonPath('code', 'CAMPO_NO_PERMITIDO');
    presentarNovedadApi($this, datosNovedad($ficha->id, ['tipo' => 'Ascenso']))
        ->assertStatus(422)->assertJsonPath('code', 'VALIDACION');
    presentarNovedadApi($this, datosNovedad($ficha->id, ['fecha_inicio' => '2026-13-45']))
        ->assertStatus(422)->assertJsonPath('code', 'VALIDACION');
    expect(DB::table('actuaciones_personal')->count())->toBe(0);
});

it('el Colaborador presenta una novedad propia y no puede elegir otra ficha ni otro tenant (US1.1, RMN-CA05)', function () {
    politicaVigente('Permisos');
    ['ficha' => $ficha] = fichaConVinculo();
    ['ficha' => $ajena] = fichaConVinculo();
    $usuario = actuarComoColaboradorDe($ficha);
    $cuerpo = datosNovedad($ficha->id);
    unset($cuerpo['colaborador_id']);

    $respuesta = $this->postJson(URL_MIS_SOLICITUDES.'/novedades', $cuerpo, ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();

    expect($respuesta->json('data.estado'))->toBe('Presentada')
        ->and($respuesta->json('data.politica.numero'))->toBe(1)
        ->and(DB::table('actuaciones_personal')->value('colaborador_id'))->toBe($ficha->id)
        ->and(DB::table('actuaciones_personal')->value('presentada_por'))->toBe($usuario->id)
        ->and((bool) DB::table('actuaciones_personal')->value('en_nombre_de_colaborador'))->toBeFalse();

    $this->postJson(URL_MIS_SOLICITUDES.'/novedades', [...$cuerpo, 'colaborador_id' => $ajena->id], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(422)->assertJsonPath('code', 'CAMPO_NO_PERMITIDO');
    expect(DB::table('actuaciones_personal')->where('colaborador_id', $ajena->id)->count())->toBe(0);
});

it('una cuenta sin ficha asociada no presenta y se le explica', function () {
    politicaVigente('Permisos');
    actuarComoUsuarioDeTenant();

    $cuerpo = datosNovedad(1);
    unset($cuerpo['colaborador_id']);
    $this->postJson(URL_MIS_SOLICITUDES.'/novedades', $cuerpo, ['Idempotency-Key' => (string) Str::uuid()])
        ->assertNotFound()->assertJsonPath('code', 'SIN_FICHA_ASOCIADA');
});
