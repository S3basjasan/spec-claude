<?php

use App\Modules\GestionPersonal\Domain\Exceptions\PosicionNoDisponible;
use App\Modules\GestionPersonal\Domain\Exceptions\VinculoSuperpuesto;
use App\Modules\GestionPersonal\Domain\Repositories\VinculoLaboralRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('si falla la segunda inserción se revierte también el vínculo (sin registros parciales)', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe(['numero_identificacion' => '80']);
    $posicion = posicionAsignable();
    DB::unprepared("CREATE TRIGGER fallo_de_prueba BEFORE INSERT ON asignaciones_laborales BEGIN SELECT RAISE(ABORT, 'FALLO_DE_PRUEBA'); END");

    $respuesta = registrarIngresoApi($this, $ficha->id, datosIngreso($posicion->id));

    DB::unprepared('DROP TRIGGER fallo_de_prueba');

    expect($respuesta->status())->toBe(500)
        ->and(DB::table('vinculos_laborales')->count())->toBe(0)
        ->and(DB::table('asignaciones_laborales')->count())->toBe(0);

    // Con el fallo retirado la misma operación puede confirmarse.
    registrarIngresoApi($this, $ficha->id, datosIngreso($posicion->id))->assertCreated();
});

it('los triggers traducen una superposición que la aplicación no vio a su error de negocio', function () {
    actuarComoAdminDeTenant();
    $repositorio = app(VinculoLaboralRepositoryInterface::class);
    $ocupante = fichaDe(['numero_identificacion' => '81']);
    $nuevo = fichaDe(['numero_identificacion' => '82']);
    $posicion = posicionAsignable();
    vinculoDe($ocupante->id, hoyDelTenant(-30), null, $posicion->id);
    vinculoDe($nuevo->id, hoyDelTenant(-100), hoyDelTenant(-60), posicionAsignable()->id);

    // Misma posición: el trigger de la posición aborta.
    $capturado = null;
    try {
        $repositorio->ejecutarBajoBloqueo(1, $nuevo->id, fn () => $repositorio->insertarIngreso(
            1, $nuevo->id, (string) Str::uuid(), str_repeat('c', 64), hoyDelTenant(), 'indefinido', $posicion->id, 1,
        ));
    } catch (Throwable $e) {
        $capturado = $e;
    }
    expect($capturado)->toBeInstanceOf(PosicionNoDisponible::class);

    // Misma ficha con un vínculo abierto: el trigger del vínculo aborta.
    $abierto = fichaDe(['numero_identificacion' => '83']);
    vinculoDe($abierto->id, hoyDelTenant(-5), null, posicionAsignable()->id);
    $capturado = null;
    try {
        $repositorio->ejecutarBajoBloqueo(1, $abierto->id, fn () => $repositorio->insertarIngreso(
            1, $abierto->id, (string) Str::uuid(), str_repeat('d', 64), hoyDelTenant(), 'indefinido', posicionAsignable()->id, 1,
        ));
    } catch (Throwable $e) {
        $capturado = $e;
    }
    expect($capturado)->toBeInstanceOf(VinculoSuperpuesto::class)
        ->and(DB::table('vinculos_laborales')->where('colaborador_id', $abierto->id)->count())->toBe(1);
});

it('una posición que cambia entre elegirla y confirmar rechaza el conjunto completo', function () {
    actuarComoAdminDeTenant();
    $ficha = fichaDe(['numero_identificacion' => '84']);
    $posicion = posicionAsignable();

    $opciones = $this->getJson("/api/colaboradores/{$ficha->id}/opciones-ingreso?fecha=".hoyDelTenant())->assertOk();
    expect(array_column($opciones->json('data.posiciones'), 'id'))->toContain($posicion->id);

    // Otro proceso ocupa la posición antes de que se confirme.
    vinculoDe(fichaDe(['numero_identificacion' => '85'])->id, hoyDelTenant(-1), null, $posicion->id);

    registrarIngresoApi($this, $ficha->id, datosIngreso($posicion->id))
        ->assertUnprocessable()->assertJsonPath('code', 'POSICION_NO_DISPONIBLE');

    expect(DB::table('vinculos_laborales')->where('colaborador_id', $ficha->id)->count())->toBe(0);
});
