<?php

use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\DB;

it('un reingreso crea un vínculo nuevo y conserva la ficha, el código y la historia (GP02-CA06)', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe(['numero_identificacion' => '60']);
    $anterior = posicionAsignable();
    $salida = hoyDelTenant(-30);
    $vinculoAnterior = vinculoDe($ficha->id, hoyDelTenant(-400), $salida, $anterior->id);
    $libre = posicionAsignable();
    $codigo = $ficha->codigo;

    $detalle = $this->getJson('/api/colaboradores/'.$ficha->id)->assertOk();
    expect($detalle->json('data.laboral.estado'))->toBe('Desvinculado');

    $respuesta = registrarIngresoApi($this, $ficha->id, datosIngreso($libre->id, ['fecha_ingreso' => $salida]))->assertCreated();

    expect($respuesta->json('data.situacion.estado'))->toBe('Activo')
        ->and(DB::table('vinculos_laborales')->where('colaborador_id', $ficha->id)->count())->toBe(2)
        ->and((string) DB::table('vinculos_laborales')->where('id', $vinculoAnterior)->value('fecha_salida'))->toBe($salida)
        ->and($ficha->fresh()->codigo)->toBe($codigo);

    $trayectoria = $this->getJson("/api/colaboradores/{$ficha->id}/trayectoria")->assertOk();
    expect(array_column($trayectoria->json('data'), 'tipo'))->toBe(['Reingreso', 'Ingreso']);
});

it('no permite ingresar antes de la salida del vínculo previo ni con un vínculo abierto', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe(['numero_identificacion' => '61']);
    vinculoDe($ficha->id, hoyDelTenant(-400), hoyDelTenant(-30), posicionAsignable()->id);

    registrarIngresoApi($this, $ficha->id, datosIngreso(posicionAsignable()->id, ['fecha_ingreso' => hoyDelTenant(-31)]))
        ->assertUnprocessable()->assertJsonPath('code', 'VINCULO_SUPERPUESTO');

    $abierta = fichaDe(['numero_identificacion' => '62']);
    vinculoDe($abierta->id, hoyDelTenant(-400), null, posicionAsignable()->id);
    registrarIngresoApi($this, $abierta->id, datosIngreso(posicionAsignable()->id))
        ->assertUnprocessable()->assertJsonPath('code', 'VINCULO_SUPERPUESTO');
});

it('un reingreso futuro no cambia el estado Desvinculado y se indica como programado', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe(['numero_identificacion' => '63']);
    vinculoDe($ficha->id, hoyDelTenant(-400), hoyDelTenant(-30), posicionAsignable()->id);

    registrarIngresoApi($this, $ficha->id, datosIngreso(posicionAsignable()->id, ['fecha_ingreso' => hoyDelTenant(20)]))->assertCreated();

    $detalle = $this->getJson('/api/colaboradores/'.$ficha->id)->assertOk();
    expect($detalle->json('data.laboral.estado'))->toBe('Desvinculado')
        ->and($detalle->json('data.laboral.proximo_ingreso.fecha_ingreso'))->toBe(hoyDelTenant(20))
        ->and($detalle->json('data.laboral.proximo_ingreso.programado'))->toBeTrue();
});

it('un reingreso no crea ni reactiva cuentas ni transfiere permisos', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe(['numero_identificacion' => '64']);
    $cuenta = UserModel::factory()->create();
    DB::table('colaborador_cuentas')->insert([
        'tenant_id' => 1, 'user_id' => $cuenta->id, 'colaborador_id' => $ficha->id,
        'puede_consultar_ficha' => false, 'puede_consultar_documentos' => false, 'puede_exportar' => false,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    vinculoDe($ficha->id, hoyDelTenant(-400), hoyDelTenant(-30), posicionAsignable()->id);
    $usuarios = UserModel::query()->count();

    registrarIngresoApi($this, $ficha->id, datosIngreso(posicionAsignable()->id))->assertCreated();

    $permisos = DB::table('colaborador_cuentas')->where('user_id', $cuenta->id)->first();
    expect(UserModel::query()->count())->toBe($usuarios)
        ->and((bool) $permisos->puede_consultar_ficha)->toBeFalse()
        ->and((bool) $permisos->puede_exportar)->toBeFalse();
});
