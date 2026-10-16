<?php

use App\Modules\GestionPersonal\Domain\ValueObjects\Lectura;

it('muestra puesto, unidad, estado y la trayectoria del más reciente al más antiguo, con el hecho futuro programado', function () {
    $ficha = fichaDe(['numero_identificacion' => '10']);
    lectorLaboralFalso($this, [
        'situacionVigente' => Lectura::disponible([$ficha->id => ['estado' => 'Activo', 'posicion_id' => 7]]),
        'trayectoria' => Lectura::disponible([
            ['fecha_efectiva' => '2020-01-10', 'tipo' => 'Ingreso'],
            ['fecha_efectiva' => '2999-01-01', 'tipo' => 'Contratacion'],
            ['fecha_efectiva' => '2023-05-01', 'tipo' => 'Movimiento', 'detalle' => 'Cambio de posición'],
        ]),
    ]);
    lectorEstructuraFalso($this, [
        'posiciones' => Lectura::disponible([7 => ['puesto' => 'Analista', 'unidad_id' => 3, 'unidad' => 'Finanzas']]),
    ]);
    actuarComoAdminDeTenant();

    $detalle = $this->getJson('/api/colaboradores/'.$ficha->id)->assertOk();

    expect($detalle->json('data.laboral'))->toBe(['disponibilidad' => 'disponible', 'estado' => 'Activo', 'puesto' => 'Analista', 'unidad' => 'Finanzas', 'proximo_ingreso' => null])
        ->and(array_column($detalle->json('data.trayectoria.hechos'), 'fecha_efectiva'))->toBe(['2999-01-01', '2023-05-01', '2020-01-10'])
        ->and(array_column($detalle->json('data.trayectoria.hechos'), 'programado'))->toBe([true, false, false]);

    $this->getJson('/api/colaboradores')->assertOk()
        ->assertJsonPath('data.0.puesto', 'Analista')->assertJsonPath('data.0.unidad', 'Finanzas')->assertJsonPath('data.0.estado', 'Activo');
});

it('distingue la caída de la fuente de la ausencia y no oculta los datos personales (GP01-RN08)', function () {
    $ficha = fichaDe(['numero_identificacion' => '11', 'nombres' => 'Visible']);
    lectorLaboralFalso($this, [
        'situacionVigente' => Lectura::noDisponible(),
        'trayectoria' => Lectura::noDisponible(),
    ]);
    actuarComoAdminDeTenant();

    $detalle = $this->getJson('/api/colaboradores/'.$ficha->id)->assertOk();

    expect($detalle->json('data.nombres'))->toBe('Visible')
        ->and($detalle->json('data.laboral.disponibilidad'))->toBe('no_disponible')
        ->and($detalle->json('data.laboral.estado'))->toBeNull()
        ->and($detalle->json('data.trayectoria.disponibilidad'))->toBe('no_disponible');

    $this->getJson('/api/colaboradores')->assertOk()
        ->assertJsonPath('data.0.laboral_disponible', false)->assertJsonPath('meta.total', 1);
});

it('con un filtro dependiente de una fuente caída responde 503 y no un listado engañoso', function () {
    fichaDe(['numero_identificacion' => '12']);
    lectorLaboralFalso($this, ['idsPorEstado' => Lectura::noDisponible()]);
    actuarComoAdminDeTenant();

    $this->getJson('/api/colaboradores?estado=SinVinculoVigente')
        ->assertStatus(503)->assertJsonPath('code', 'INFORMACION_NO_DISPONIBLE');
});

it('filtra por estado y unidad con lectores disponibles y excluye a los vinculados de Sin vínculo vigente', function () {
    $con = fichaDe(['numero_identificacion' => '13', 'apellidos' => 'Con']);
    $sin = fichaDe(['numero_identificacion' => '14', 'apellidos' => 'Sin']);
    lectorEstructuraFalso($this, [
        'unidades' => Lectura::disponible([['id' => 3, 'nombre' => 'Finanzas']]),
        'posicionesPorUnidad' => Lectura::disponible([7]),
    ]);
    actuarComoAdminDeTenant();

    lectorLaboralFalso($this, ['idsPorEstado' => Lectura::disponible([$sin->id])]);
    $this->getJson('/api/colaboradores?estado=SinVinculoVigente')->assertOk()
        ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $sin->id);

    lectorLaboralFalso($this, [
        'idsPorEstado' => Lectura::disponible([$con->id]),
        'colaboradoresEnPosiciones' => Lectura::disponible([$con->id]),
        'situacionVigente' => Lectura::disponible([$con->id => ['estado' => 'Activo', 'posicion_id' => null]]),
    ]);
    $this->getJson('/api/colaboradores?estado=Activo')->assertOk()
        ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $con->id);
    $this->getJson('/api/colaboradores?unidad_id=3')->assertOk()
        ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $con->id);
    $this->getJson('/api/colaboradores?unidad_id=99')->assertUnprocessable();
});

it('busca por puesto con las lecturas por lote', function () {
    $analista = fichaDe(['numero_identificacion' => '15', 'nombres' => 'Pedro', 'apellidos' => 'Zeta']);
    fichaDe(['numero_identificacion' => '16', 'nombres' => 'Pablo', 'apellidos' => 'Alfa']);
    lectorEstructuraFalso($this, ['posicionesPorPuesto' => Lectura::disponible([7])]);
    lectorLaboralFalso($this, ['colaboradoresEnPosiciones' => Lectura::disponible([$analista->id])]);
    actuarComoAdminDeTenant();

    $this->getJson('/api/colaboradores?q=analista')->assertOk()
        ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $analista->id);
});
