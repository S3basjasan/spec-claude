<?php

it('rechaza desactivar una unidad con una posición suprimida que aún tiene una asignación vigente (FR-016b)', function () {
    actuarComoAdminDeTenant();
    $a = arbolDeEjemplo();
    $suprimida = posicionAsignable(['unidad_organizacional_id' => $a['reclutamiento']->id, 'estado' => 'Suprimida']);
    vinculoDe(fichaDe(['numero_identificacion' => '210'])->id, hoyDelTenant(-30), null, $suprimida->id);

    $this->patchJson("/api/unidades/{$a['reclutamiento']->id}/estado", ['estado' => 'Inactiva'])
        ->assertUnprocessable()
        ->assertJsonPath('unidades_con_posiciones.0.id', $a['reclutamiento']->id)
        ->assertJsonPath('unidades_con_posiciones.0.cantidad', 1);

    expect($a['reclutamiento']->fresh()->estado)->toBe('Activa');
});

it('bloquea la desactivación de una rama completa por la asignación de una dependiente', function () {
    actuarComoAdminDeTenant();
    $a = arbolDeEjemplo();
    $suprimida = posicionAsignable(['unidad_organizacional_id' => $a['reclutamiento']->id, 'estado' => 'Suprimida']);
    vinculoDe(fichaDe(['numero_identificacion' => '211'])->id, hoyDelTenant(15), null, $suprimida->id);

    $respuesta = $this->patchJson("/api/unidades/{$a['talento']->id}/estado", ['estado' => 'Inactiva']);

    $respuesta->assertUnprocessable();
    expect(array_column($respuesta->json('unidades_con_posiciones'), 'id'))->toContain($a['reclutamiento']->id);

    expect($a['reclutamiento']->fresh()->estado)->toBe('Activa');
});

it('una asignación terminada no impide desactivar', function () {
    actuarComoAdminDeTenant();
    $a = arbolDeEjemplo();
    $suprimida = posicionAsignable(['unidad_organizacional_id' => $a['reclutamiento']->id, 'estado' => 'Suprimida']);
    vinculoDe(fichaDe(['numero_identificacion' => '212'])->id, hoyDelTenant(-90), hoyDelTenant(-10), $suprimida->id);

    $this->patchJson("/api/unidades/{$a['reclutamiento']->id}/estado", ['estado' => 'Inactiva'])
        ->assertOk()->assertJsonPath('data.estado', 'Inactiva');
});
