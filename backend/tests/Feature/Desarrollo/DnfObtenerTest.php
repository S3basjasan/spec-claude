<?php

it('devuelve el detalle de una DNF individual con brechas, autor y fecha', function () {
    dnfFalsos();
    $admin = actuarComoAdminDeTenant();
    $dnf = dnfDeEjemplo([10 => [[1, 'A', 'C'], [2, 'B', 'B'], [3, 'B', null]]], creadoPor: $admin->id, omitidas: 1);

    $respuesta = $this->getJson('/api/dnf/'.$dnf->id)->assertOk();

    $datos = $respuesta->json('data');
    expect($datos['codigo'])->toBe('DNF-0001')
        ->and($datos['generado_por']['id'])->toBe($admin->id)
        ->and($datos['fecha'])->not->toBeEmpty()
        ->and($datos['plan_formacion'])->toBeNull()
        ->and($datos['competencias_omitidas'])->toBe(1)
        ->and($datos['colaboradores'][0]['brechas'])->toHaveCount(3)
        ->and($datos['colaboradores'][0]['brechas'][0])->toMatchArray(['competencia_id' => 1, 'prioridad' => 'Alta', 'estado' => 'ConBrecha'])
        ->and($respuesta->json('avisos'))->toBe(['competencias_omitidas' => 1, 'sin_evaluacion' => 1, 'excluidos' => []]);
});

it('en una masiva devuelve solo el resumen por colaborador, y las brechas de uno con ?colaborador_id=', function () {
    dnfFalsos();
    actuarComoAdminDeTenant();
    $dnf = dnfDeEjemplo([10 => [[1, 'A', 'C']], 11 => [[1, 'A', 'D'], [2, 'B', 'B']]], 'Departamento', 5);

    $resumen = $this->getJson('/api/dnf/'.$dnf->id)->assertOk()->json('data');
    expect($resumen['colaboradores'])->toHaveCount(2)
        ->and($resumen['colaboradores'][0])->not->toHaveKey('brechas')
        ->and($resumen['colaboradores'][1]['cantidad_brechas'])->toBe(1)
        ->and($resumen['departamento'])->toBe(['id' => 5, 'nombre' => 'Unidad 5']);

    $uno = $this->getJson('/api/dnf/'.$dnf->id.'?colaborador_id=11')->assertOk()->json('data');
    expect($uno['colaboradores'])->toHaveCount(1)
        ->and($uno['colaboradores'][0]['colaborador_id'])->toBe(11)
        ->and($uno['colaboradores'][0]['brechas'])->toHaveCount(2);
});

it('responde 404 si el colaborador no pertenece a la DNF', function () {
    dnfFalsos();
    actuarComoAdminDeTenant();
    $dnf = dnfDeEjemplo([10 => [[1, 'A', 'C']], 11 => [[1, 'A', 'D']]], 'Departamento', 5);

    $this->getJson('/api/dnf/'.$dnf->id.'?colaborador_id=99')->assertNotFound();
});

it('responde 404 para una DNF inexistente o de otro tenant', function () {
    dnfFalsos();
    actuarComoAdminDeTenant();
    tenantDos();
    $ajena = dnfDeEjemplo([10 => [[1, 'A', 'C']]], tenantId: 2);

    $this->getJson('/api/dnf/9999')->assertNotFound()->assertJsonPath('message', 'La DNF no existe.');
    $this->getJson('/api/dnf/'.$ajena->id)->assertNotFound();
});

it('valida el parámetro colaborador_id', function () {
    dnfFalsos();
    actuarComoAdminDeTenant();
    $dnf = dnfDeEjemplo([10 => [[1, 'A', 'C']]]);

    $this->getJson('/api/dnf/'.$dnf->id.'?colaborador_id=abc')->assertUnprocessable()->assertJsonValidationErrors('colaborador_id');
});

it('un id no numérico no colisiona con las rutas fijas', function () {
    dnfFalsos();
    actuarComoAdminDeTenant();

    $this->getJson('/api/dnf/abc')->assertNotFound();
    $this->getJson('/api/dnf/opciones')->assertOk();
    $this->getJson('/api/dnf/mi-diagnostico')->assertOk();
});

it('exige autenticación y rol de administrador', function () {
    dnfFalsos();
    $dnf = dnfDeEjemplo([10 => [[1, 'A', 'C']]]);

    $this->getJson('/api/dnf/'.$dnf->id)->assertUnauthorized();

    actuarComoUsuarioDeTenant();
    $this->getJson('/api/dnf/'.$dnf->id)->assertForbidden();
});

it('no permite modificar ni eliminar una DNF por la API', function (string $metodo) {
    dnfFalsos();
    actuarComoAdminDeTenant();
    $dnf = dnfDeEjemplo([10 => [[1, 'A', 'C']]]);

    $this->json($metodo, '/api/dnf/'.$dnf->id)->assertStatus(405);
})->with(['PUT', 'PATCH', 'DELETE']);
