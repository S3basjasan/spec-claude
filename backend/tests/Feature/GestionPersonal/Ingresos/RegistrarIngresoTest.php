<?php

use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\DB;

it('registra un ingreso actual: crea vínculo y asignación y deja la ficha Activo (GP02-CA01)', function () {
    $admin = actuarComoAdminDeTenant();
    $ficha = fichaDe(['numero_identificacion' => '10']);
    $posicion = posicionAsignable();

    $respuesta = registrarIngresoApi($this, $ficha->id, datosIngreso($posicion->id))->assertCreated();

    expect($respuesta->json('data.vinculo.fecha_ingreso'))->toBe(hoyDelTenant())
        ->and($respuesta->json('data.vinculo.tipo_contrato'))->toBe(['clave' => 'indefinido', 'etiqueta' => 'Indefinido'])
        ->and($respuesta->json('data.asignacion.posicion_id'))->toBe($posicion->id)
        ->and($respuesta->json('data.asignacion.fecha_inicio'))->toBe(hoyDelTenant())
        ->and($respuesta->json('data.situacion.estado'))->toBe('Activo')
        ->and(DB::table('vinculos_laborales')->count())->toBe(1)
        ->and(DB::table('asignaciones_laborales')->count())->toBe(1)
        ->and((int) DB::table('vinculos_laborales')->value('created_by'))->toBe($admin->id);

    $detalle = $this->getJson('/api/colaboradores/'.$ficha->id)->assertOk();
    expect($detalle->json('data.laboral.estado'))->toBe('Activo')
        ->and($detalle->json('data.laboral.puesto'))->not->toBeNull()
        ->and($detalle->json('data.laboral.unidad'))->not->toBeNull();
});

it('conserva la fecha pasada como efectiva y el momento actual como registro (GP02-CA02)', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe(['numero_identificacion' => '11']);
    $posicion = posicionAsignable();
    $pasada = hoyDelTenant(-240);

    $respuesta = registrarIngresoApi($this, $ficha->id, datosIngreso($posicion->id, ['fecha_ingreso' => $pasada]))->assertCreated();

    expect($respuesta->json('data.vinculo.fecha_ingreso'))->toBe($pasada)
        ->and($respuesta->json('data.vinculo.registrado_en'))->toStartWith(now()->utc()->toDateString());
});

it('no crea cuentas ni toca permisos al registrar el ingreso (GP02-CA08)', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe(['numero_identificacion' => '12']);
    $posicion = posicionAsignable();
    $usuarios = UserModel::query()->count();
    $cuentas = DB::table('colaborador_cuentas')->count();

    registrarIngresoApi($this, $ficha->id, datosIngreso($posicion->id))->assertCreated();

    expect(UserModel::query()->count())->toBe($usuarios)
        ->and(DB::table('colaborador_cuentas')->count())->toBe($cuentas);
});

it('valida fecha, contrato y posición por campo y no crea nada', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe(['numero_identificacion' => '13']);

    registrarIngresoApi($this, $ficha->id, [])->assertUnprocessable()
        ->assertJsonValidationErrors(['fecha_ingreso', 'tipo_contrato', 'posicion_id']);
    registrarIngresoApi($this, $ficha->id, datosIngreso(1, ['fecha_ingreso' => '15/10/2026']))
        ->assertUnprocessable()->assertJsonValidationErrors(['fecha_ingreso']);
    registrarIngresoApi($this, $ficha->id, datosIngreso(1, ['tipo_contrato' => 'honorarios']))
        ->assertUnprocessable()->assertJsonPath('code', 'CONTRATO_NO_HABILITADO');

    expect(DB::table('vinculos_laborales')->count())->toBe(0);
});

it('rechaza campos no declarados como fecha_salida, estado y salario', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe(['numero_identificacion' => '14']);
    $posicion = posicionAsignable();

    foreach (['fecha_salida' => '2030-01-01', 'estado' => 'Activo', 'salario' => 1000, 'tenant_id' => 2, 'vinculo_id' => 3] as $campo => $valor) {
        registrarIngresoApi($this, $ficha->id, datosIngreso($posicion->id, [$campo => $valor]))
            ->assertUnprocessable()->assertJsonPath('code', 'CAMPO_NO_PERMITIDO');
    }

    expect(DB::table('vinculos_laborales')->count())->toBe(0);
});

it('no ofrece corrección, cancelación ni reprogramación', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe(['numero_identificacion' => '15']);
    $posicion = posicionAsignable();
    registrarIngresoApi($this, $ficha->id, datosIngreso($posicion->id))->assertCreated();
    $vinculo = DB::table('vinculos_laborales')->value('id');

    foreach (['putJson', 'patchJson', 'deleteJson'] as $metodo) {
        expect($this->{$metodo}("/api/colaboradores/{$ficha->id}/ingresos/$vinculo", [])->status())->toBeIn([404, 405]);
    }
});
