<?php

use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDePdi;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoDePdi;

it('con brechas, crear plan e incluir en programa quedan habilitados mientras no haya un plan abierto del tipo', function () {
    actuarComoAdminDeTenant();
    adecuacionFalsos();
    $a = adecuacionEnBd([['objetivo' => 'B', 'valor' => 'C', 'tiempo' => 6]]);

    $acciones = $this->getJson("/api/adecuaciones/{$a->id}")->assertOk()->json('data.acciones');

    expect($acciones['crear_plan'])->toBe(['habilitado' => true, 'motivo' => ''])
        ->and($acciones['incluir_programa'])->toBe(['habilitado' => true, 'motivo' => '']);
});

it('un plan abierto del tipo deshabilita el botón correspondiente con su motivo', function () {
    actuarComoAdminDeTenant();
    adecuacionFalsos();
    $a = adecuacionEnBd([['objetivo' => 'B', 'valor' => 'C', 'tiempo' => 6]]);
    pdiEnBd(['colaborador' => 10, 'tipo' => TipoDePdi::Carrera, 'puesto' => 2, 'estado' => EstadoDePdi::Acordado]);

    $acciones = $this->getJson("/api/adecuaciones/{$a->id}")->assertOk()->json('data.acciones');

    expect($acciones['crear_plan'])->toBe(['habilitado' => false, 'motivo' => 'La persona ya tiene un plan de Carrera abierto.'])
        ->and($acciones['incluir_programa']['habilitado'])->toBeTrue();
});

it('un plan Cerrado del tipo no deshabilita el botón', function () {
    actuarComoAdminDeTenant();
    adecuacionFalsos();
    $a = adecuacionEnBd([['objetivo' => 'B', 'valor' => 'C', 'tiempo' => 6]]);
    pdiEnBd(['colaborador' => 10, 'tipo' => TipoDePdi::Talento, 'puesto' => 2, 'estado' => EstadoDePdi::Cerrado]);

    expect($this->getJson("/api/adecuaciones/{$a->id}")->json('data.acciones.incluir_programa.habilitado'))->toBeTrue();
});

it('sin brechas el motivo es «No hay brechas.»', function () {
    actuarComoAdminDeTenant();
    adecuacionFalsos();
    $a = adecuacionEnBd([['objetivo' => 'B', 'valor' => 'B']]);

    $acciones = $this->getJson("/api/adecuaciones/{$a->id}")->assertOk()->json('data.acciones');

    expect($acciones['crear_plan'])->toBe(['habilitado' => false, 'motivo' => 'No hay brechas.'])
        ->and($acciones['incluir_programa']['motivo'])->toBe('No hay brechas.');
});

it('al guardar una adecuación con brechas la respuesta trae las acciones habilitadas', function () {
    [, $inscripcion] = escenarioDeAdecuacion();

    $acciones = $this->postJson('/api/adecuaciones', ['colaborador_id' => 10, 'inscripcion_id' => $inscripcion->id, 'tiempos_cierre' => [['dimension' => 'Competencia', 'elemento_id' => 1, 'meses' => 6]]])
        ->assertCreated()->json('data.acciones');

    expect($acciones['crear_plan']['habilitado'])->toBeTrue()->and($acciones['incluir_programa']['habilitado'])->toBeTrue();
});
