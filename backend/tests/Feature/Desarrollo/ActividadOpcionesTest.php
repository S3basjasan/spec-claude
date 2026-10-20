<?php

beforeEach(function () {
    $this->falsos = actividadFalsos();
    $this->admin = actuarComoAdminDeTenant();
});

it('devuelve departamentos activos, competencias y planes Ejecutando o Cerrado del tenant', function () {
    [$ejecutando] = accionEnPlan(['nombre' => 'A']);
    [$cerrado] = accionEnPlan(['nombre' => 'B'], 'Cerrado');
    accionEnPlan(['nombre' => 'C'], 'Aprobado');

    $r = $this->getJson('/api/actividades/opciones')->assertOk();

    expect($r->json('data.departamentos'))->toBe([['id' => 1, 'nombre' => 'Tecnología'], ['id' => 2, 'nombre' => 'Talento Humano']])
        ->and(collect($r->json('data.competencias'))->pluck('id')->all())->toBe([1, 2, 3, 9])
        ->and(collect($r->json('data.planes'))->pluck('id')->sort()->values()->all())->toBe([$ejecutando, $cerrado])
        ->and($r->json('data.planes.0'))->toHaveKeys(['id', 'codigo', 'nombre', 'anio', 'estado']);
});

it('no ofrece planes de otro tenant y exige administrador', function () {
    accionEnPlan();
    actuarComoAdminDeTenant(2);
    $this->getJson('/api/actividades/opciones')->assertOk()->assertJsonPath('data.planes', []);

    actuarComoUsuarioDeTenant();
    $this->getJson('/api/actividades/opciones')->assertForbidden();
    app('auth')->forgetGuards();
    $this->getJson('/api/actividades/opciones')->assertUnauthorized();
});
