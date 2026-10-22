<?php

it('con brechas, crear plan e incluir en programa siguen deshabilitados hasta que exista el Plan de Desarrollo Individual', function () {
    actuarComoAdminDeTenant();
    adecuacionFalsos();
    $a = adecuacionEnBd([['objetivo' => 'B', 'valor' => 'C', 'tiempo' => 6]]);

    $acciones = $this->getJson("/api/adecuaciones/{$a->id}")->assertOk()->json('data.acciones');

    expect($acciones['crear_plan'])->toBe(['habilitado' => false, 'motivo' => 'Disponible cuando exista el Plan de Desarrollo Individual.'])
        ->and($acciones['incluir_programa'])->toBe(['habilitado' => false, 'motivo' => 'Disponible cuando exista el Plan de Desarrollo Individual.']);
});

it('sin brechas el motivo es «No hay brechas.»', function () {
    actuarComoAdminDeTenant();
    adecuacionFalsos();
    $a = adecuacionEnBd([['objetivo' => 'B', 'valor' => 'B']]);

    $acciones = $this->getJson("/api/adecuaciones/{$a->id}")->assertOk()->json('data.acciones');

    expect($acciones['crear_plan'])->toBe(['habilitado' => false, 'motivo' => 'No hay brechas.'])
        ->and($acciones['incluir_programa']['motivo'])->toBe('No hay brechas.');
});

it('al guardar una adecuación la respuesta trae las mismas acciones deshabilitadas', function () {
    [, $inscripcion] = escenarioDeAdecuacion();

    $acciones = $this->postJson('/api/adecuaciones', ['colaborador_id' => 10, 'inscripcion_id' => $inscripcion->id, 'tiempos_cierre' => [['dimension' => 'Competencia', 'elemento_id' => 1, 'meses' => 6]]])
        ->assertCreated()->json('data.acciones');

    expect($acciones['crear_plan']['habilitado'])->toBeFalse()->and($acciones['incluir_programa']['habilitado'])->toBeFalse();
});
