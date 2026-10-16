<?php

it('devuelve los colaboradores y departamentos evaluables del tenant', function () {
    dnfFalsos([
        'evaluables' => [evaluableDe(11, 1, 5), evaluableDe(10, 2, 5)],
        'excluidos' => [['colaborador_id' => 12, 'nombre' => 'Sin vínculo', 'motivo' => 'SinVinculoVigente']],
        'departamentos' => [['id' => 5, 'nombre' => 'Tecnología', 'colaboradores_evaluables' => 2]],
    ]);
    actuarComoAdminDeTenant();

    $respuesta = $this->getJson('/api/dnf/opciones')->assertOk();

    expect($respuesta->json('colaboradores'))->toBe([
        ['id' => 10, 'nombre' => 'Colaborador 10', 'puesto' => 'Puesto 2', 'departamento_id' => 5],
        ['id' => 11, 'nombre' => 'Colaborador 11', 'puesto' => 'Puesto 1', 'departamento_id' => 5],
    ])
        ->and($respuesta->json('departamentos'))->toBe([['id' => 5, 'nombre' => 'Tecnología', 'colaboradores_evaluables' => 2]])
        ->and(collect($respuesta->json('colaboradores'))->pluck('id')->all())->not->toContain(12); // FR-002a
});

it('devuelve listas vacías si no hay evaluables', function () {
    dnfFalsos();
    actuarComoAdminDeTenant();

    $this->getJson('/api/dnf/opciones')->assertOk()->assertExactJson(['colaboradores' => [], 'departamentos' => []]);
});

it('exige autenticación y rol de administrador', function () {
    dnfFalsos();

    $this->getJson('/api/dnf/opciones')->assertUnauthorized();

    actuarComoUsuarioDeTenant();
    $this->getJson('/api/dnf/opciones')->assertForbidden();
});
