<?php

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\PosicionModel;

it('rechaza desactivar un puesto con una posición suprimida que aún tiene una asignación vigente (FR-016b)', function () {
    actuarComoAdminDeTenant();
    $puesto = puestoDe(['nombre' => 'Analista']);
    $suprimida = posicionAsignable(['puesto_id' => $puesto->id, 'estado' => 'Suprimida']);
    vinculoDe(fichaDe(['numero_identificacion' => '200'])->id, hoyDelTenant(-30), null, $suprimida->id);

    $this->patchJson("/api/puestos/{$puesto->id}/estado", ['estado' => 'Inactivo'])
        ->assertUnprocessable()
        ->assertJsonPath('posiciones_en_uso', 1)
        ->assertJsonPath('message', 'No se puede desactivar el puesto: tiene 1 posición ocupada, vacante o con asignación vigente.');

    expect($puesto->fresh()->estado)->toBe('Activo');
});

it('la asignación futura de una posición suprimida también bloquea; la terminada no', function () {
    actuarComoAdminDeTenant();
    $futuro = puestoDe(['nombre' => 'Con futuro']);
    $posicionFutura = posicionAsignable(['puesto_id' => $futuro->id, 'estado' => 'Suprimida']);
    vinculoDe(fichaDe(['numero_identificacion' => '201'])->id, hoyDelTenant(30), null, $posicionFutura->id);

    $this->patchJson("/api/puestos/{$futuro->id}/estado", ['estado' => 'Inactivo'])->assertUnprocessable();

    $terminado = puestoDe(['nombre' => 'Terminado']);
    $posicionTerminada = posicionAsignable(['puesto_id' => $terminado->id, 'estado' => 'Suprimida']);
    vinculoDe(fichaDe(['numero_identificacion' => '202'])->id, hoyDelTenant(-90), hoyDelTenant(-10), $posicionTerminada->id);

    $this->patchJson("/api/puestos/{$terminado->id}/estado", ['estado' => 'Inactivo'])
        ->assertOk()->assertJsonPath('data.estado', 'Inactivo');
});

it('conserva el comportamiento anterior: posiciones suprimidas sin asignaciones no bloquean y las vacantes sí', function () {
    actuarComoAdminDeTenant();
    $libre = puestoDe(['nombre' => 'Libre'], suprimidas: 2);
    $enUso = puestoDe(['nombre' => 'En uso'], vacantes: 1);

    $this->patchJson("/api/puestos/{$libre->id}/estado", ['estado' => 'Inactivo'])->assertOk();
    $this->patchJson("/api/puestos/{$enUso->id}/estado", ['estado' => 'Inactivo'])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'No se puede desactivar el puesto: tiene 1 posición ocupada o vacante.');
    expect(PosicionModel::query()->where('puesto_id', $enUso->id)->count())->toBe(1);
});

it('un puesto ya inactivo con una posición suprimida asignada sigue sin cambios', function () {
    actuarComoAdminDeTenant();
    $puesto = puestoDe(['nombre' => 'Inactivo', 'estado' => 'Inactivo']);
    $suprimida = posicionAsignable(['puesto_id' => $puesto->id, 'estado' => 'Suprimida']);
    vinculoDe(fichaDe(['numero_identificacion' => '203'])->id, hoyDelTenant(-30), null, $suprimida->id);

    $this->patchJson("/api/puestos/{$puesto->id}/estado", ['estado' => 'Inactivo'])->assertOk();
});
