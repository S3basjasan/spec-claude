<?php

use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Carbon;

afterEach(fn () => Carbon::setTestNow());

it('un ingreso futuro es Sin vínculo vigente y programado, y pasa a Activo al llegar la fecha (GP02-CA03)', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-15 12:00:00', 'America/Guayaquil'));
    actuarComoAdminDeTenant();
    $ficha = fichaDe(['numero_identificacion' => '70']);
    $posicion = posicionAsignable();

    registrarIngresoApi($this, $ficha->id, datosIngreso($posicion->id, ['fecha_ingreso' => '2026-11-01']))->assertCreated();

    $antes = $this->getJson("/api/colaboradores/{$ficha->id}/situacion-laboral")->assertOk();
    expect($antes->json('data.estado'))->toBe('SinVinculoVigente')
        ->and($antes->json('data.vinculo_vigente'))->toBeNull()
        ->and($antes->json('data.proximo_ingreso.fecha_ingreso'))->toBe('2026-11-01')
        ->and($antes->json('data.proximo_ingreso.programado'))->toBeTrue();

    $trayectoria = $this->getJson("/api/colaboradores/{$ficha->id}/trayectoria")->assertOk();
    expect($trayectoria->json('data.0.programado'))->toBeTrue();

    Carbon::setTestNow(Carbon::parse('2026-10-31 23:59:00', 'America/Guayaquil'));
    expect($this->getJson("/api/colaboradores/{$ficha->id}/situacion-laboral")->json('data.estado'))->toBe('SinVinculoVigente');

    Carbon::setTestNow(Carbon::parse('2026-11-01 00:01:00', 'America/Guayaquil'));
    $despues = $this->getJson("/api/colaboradores/{$ficha->id}/situacion-laboral")->assertOk();
    expect($despues->json('data.estado'))->toBe('Activo')
        ->and($despues->json('data.vinculo_vigente.fecha_ingreso'))->toBe('2026-11-01')
        ->and($despues->json('data.proximo_ingreso'))->toBeNull();
});

it('la fecha de calendario es la de la zona horaria del tenant, no la del servidor', function () {
    // 03:00 UTC del 1 de noviembre es todavía el 31 de octubre en Guayaquil (UTC-5).
    Carbon::setTestNow(Carbon::parse('2026-11-01 03:00:00', 'UTC'));
    actuarComoAdminDeTenant();
    $ficha = fichaDe(['numero_identificacion' => '71']);
    vinculoDe($ficha->id, '2026-11-01', null, posicionAsignable()->id);

    expect($this->getJson("/api/colaboradores/{$ficha->id}/situacion-laboral")->json('data.estado'))->toBe('SinVinculoVigente');

    Carbon::setTestNow(Carbon::parse('2026-11-01 05:00:00', 'UTC'));
    expect($this->getJson("/api/colaboradores/{$ficha->id}/situacion-laboral")->json('data.estado'))->toBe('Activo');
});

it('el estado laboral no depende del estado de la cuenta', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe(['numero_identificacion' => '72']);
    vinculoDe($ficha->id, hoyDelTenant(-10), null, posicionAsignable()->id);
    $cuenta = UserModel::factory()->create();
    DB::table('colaborador_cuentas')->insert([
        'tenant_id' => 1, 'user_id' => $cuenta->id, 'colaborador_id' => $ficha->id,
        'puede_consultar_ficha' => true, 'puede_consultar_documentos' => false, 'puede_exportar' => false,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $antes = $this->getJson("/api/colaboradores/{$ficha->id}/situacion-laboral")->json('data.estado');
    $cuenta->tokens()->delete();
    $cuenta->forceFill(['role' => 'user', 'password' => 'otra-clave'])->save();
    $despues = $this->getJson("/api/colaboradores/{$ficha->id}/situacion-laboral")->json('data.estado');

    expect($antes)->toBe('Activo')->and($despues)->toBe('Activo');
});

it('el listado y el filtro de estados reflejan Activo, Desvinculado y Sin vínculo vigente', function () {
    actuarComoAdminDeTenant();
    $activo = fichaDe(['numero_identificacion' => '73', 'apellidos' => 'Aaa']);
    $desvinculado = fichaDe(['numero_identificacion' => '74', 'apellidos' => 'Bbb']);
    $sin = fichaDe(['numero_identificacion' => '75', 'apellidos' => 'Ccc']);
    $programado = fichaDe(['numero_identificacion' => '76', 'apellidos' => 'Ddd']);
    vinculoDe($activo->id, hoyDelTenant(-10), null, posicionAsignable()->id);
    vinculoDe($desvinculado->id, hoyDelTenant(-200), hoyDelTenant(-20), posicionAsignable()->id);
    vinculoDe($programado->id, hoyDelTenant(15), null, posicionAsignable()->id);

    $filtros = $this->getJson('/api/colaboradores/filtros')->assertOk();
    expect(array_column($filtros->json('data.estados'), 'valor'))->toBe(['Todos', 'Activo', 'Desvinculado', 'SinVinculoVigente']);

    $ids = fn (string $estado) => array_column($this->getJson('/api/colaboradores?estado='.$estado)->json('data'), 'id');

    expect($ids('Activo'))->toBe([$activo->id])
        ->and($ids('Desvinculado'))->toBe([$desvinculado->id])
        ->and($ids('SinVinculoVigente'))->toBe([$sin->id, $programado->id]);

    $lista = $this->getJson('/api/colaboradores')->json('data');
    expect(array_column($lista, 'estado'))->toBe(['Activo', 'Desvinculado', 'SinVinculoVigente', 'SinVinculoVigente'])
        ->and($lista[0]['puesto'])->not->toBeNull();
});
