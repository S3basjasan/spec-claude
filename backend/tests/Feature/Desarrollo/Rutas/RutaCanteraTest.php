<?php

beforeEach(function () {
    rutasFalsos(['vigentes' => [10 => 'Ana', 11 => 'Luis', 12 => 'Marta', 13 => 'Pablo', 14 => 'Eva']]);
    $this->ruta = rutaEnBd([1, 2, 3], 'Vigente');
    [$this->n1, $this->n2, $this->n3] = $this->ruta['niveles'];
});

it('el detalle de la ruta cuenta los colaboradores Activos por nivel actual', function () {
    actuarComoAdminDeTenant();
    inscripcionEnBd($this->ruta['id'], 10, $this->n1, $this->n3);
    inscripcionEnBd($this->ruta['id'], 11, $this->n1, $this->n2);
    inscripcionEnBd($this->ruta['id'], 12, $this->n3, null);

    $d = $this->getJson("/api/rutas/{$this->ruta['id']}")->assertOk()->json('data');

    expect(array_column($d['niveles'], 'inscritos_activos'))->toBe([2, 0, 1])
        ->and($d['sin_nivel'])->toBe(0)->and($d['inscritos_count'])->toBe(3);
});

it('las Finalizadas y las Retiradas no cuentan en la cantera', function () {
    actuarComoAdminDeTenant();
    inscripcionEnBd($this->ruta['id'], 10, $this->n2, $this->n3);
    inscripcionEnBd($this->ruta['id'], 11, $this->n3, null, 'Finalizada');
    inscripcionEnBd($this->ruta['id'], 12, $this->n1, $this->n2, 'Retirada', motivo: 'Cambio');

    $d = $this->getJson("/api/rutas/{$this->ruta['id']}")->assertOk()->json('data');

    expect(array_column($d['niveles'], 'inscritos_activos'))->toBe([0, 1, 0])->and($d['inscritos_count'])->toBe(1);
});

it('las inscripciones sin nivel actual se informan aparte, no en ningún nivel', function () {
    actuarComoAdminDeTenant();
    inscripcionEnBd($this->ruta['id'], 10, null, $this->n2);
    inscripcionEnBd($this->ruta['id'], 11, null, null);
    inscripcionEnBd($this->ruta['id'], 12, $this->n1, null);

    $d = $this->getJson("/api/rutas/{$this->ruta['id']}")->assertOk()->json('data');

    expect(array_column($d['niveles'], 'inscritos_activos'))->toBe([1, 0, 0])->and($d['sin_nivel'])->toBe(2)->and($d['inscritos_count'])->toBe(3);
});

it('el recuento de inscritos de la lista coincide con la cantera', function () {
    actuarComoAdminDeTenant();
    inscripcionEnBd($this->ruta['id'], 10, $this->n1, null);
    inscripcionEnBd($this->ruta['id'], 11, null, null);

    expect($this->getJson('/api/rutas')->assertOk()->json('data.0.inscritos_count'))->toBe(2);
});

it('el colaborador no recibe la cantera de una ruta', function () {
    [, $yo] = colaboradorConCuentaDePlan();
    inscripcionEnBd($this->ruta['id'], $yo, $this->n1, $this->n2);
    inscripcionEnBd($this->ruta['id'], 11, $this->n1, $this->n2);

    $json = json_encode($this->getJson('/api/rutas/mi-ruta')->assertOk()->json());

    expect($json)->not->toContain('inscritos_activos')->not->toContain('sin_nivel')->not->toContain('inscritos_count');
});
