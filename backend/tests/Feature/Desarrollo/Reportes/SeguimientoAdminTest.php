<?php

beforeEach(function () {
    $this->falsos = actividadFalsos(['hoy' => '2026-10-17', 'vigentes' => [10 => 'Ana', 11 => 'Luis', 12 => 'Eva', 13 => 'Pedro']]);
    departamentosActuales([10 => 1, 11 => 1, 12 => 2, 13 => 2]);
    $this->seguimiento = fn (array $filtros = []) => $this->getJson('/api/reportes/seguimiento?'.http_build_query($filtros));
});

it('entrega por actividad Finalizada la tasa de respuesta y los promedios de cada hito', function () {
    actuarComoAdminDeTenant();
    $a = actividadConAsistencia([10 => 'Presente', 11 => 'Presente', 12 => 'Presente', 13 => 'Ausente']);
    seguimientoDe($a['actividad'], 10, 30, 5, 4);
    seguimientoDe($a['actividad'], 11, 30, 3, 2);
    seguimientoDe($a['actividad'], 10, 60, 4, 4);

    $r = ($this->seguimiento)()->assertOk()->assertJsonPath('meta.total', 1);
    $fila = $r->json('data.0');

    expect($fila['actividad_id'])->toBe($a['actividad'])->and($fila['elegibles'])->toBe(3)->and($fila['alerta_reingreso'])->toBeFalse()
        ->and($fila['hitos'][0])->toEqual(['hito' => 30, 'vencimiento' => '2026-04-12', 'respuestas' => 2, 'tasa_respuesta' => 66.7, 'promedio_aplicacion' => 4.0, 'promedio_apoyo' => 3.0])
        ->and($fila['hitos'][1]['respuestas'])->toBe(1)->and($fila['hitos'][1]['tasa_respuesta'])->toBe(33.3)
        ->and($fila['hitos'][2])->toEqual(['hito' => 90, 'vencimiento' => '2026-06-11', 'respuestas' => 0, 'tasa_respuesta' => 0.0, 'promedio_aplicacion' => null, 'promedio_apoyo' => null]);
});

it('marca la alerta de reingreso con una caída de 1 punto y 3 respuestas en ambos hitos', function () {
    actuarComoAdminDeTenant();
    $a = actividadConAsistencia([10 => 'Presente', 11 => 'Presente', 12 => 'Presente']);
    foreach ([10 => 5, 11 => 4, 12 => 3] as $c => $n) {
        seguimientoDe($a['actividad'], $c, 30, $n);
    }
    foreach ([10 => 4, 11 => 3, 12 => 2] as $c => $n) {
        seguimientoDe($a['actividad'], $c, 60, $n);
    }

    ($this->seguimiento)()->assertOk()->assertJsonPath('data.0.alerta_reingreso', true);
});

it('no marca la alerta si algún hito tiene menos de 3 respuestas', function () {
    actuarComoAdminDeTenant();
    $a = actividadConAsistencia([10 => 'Presente', 11 => 'Presente', 12 => 'Presente']);
    foreach ([10 => 5, 11 => 5, 12 => 5] as $c => $n) {
        seguimientoDe($a['actividad'], $c, 30, $n);
    }
    seguimientoDe($a['actividad'], 10, 60, 1);
    seguimientoDe($a['actividad'], 11, 60, 1);

    ($this->seguimiento)()->assertOk()->assertJsonPath('data.0.alerta_reingreso', false);
});

it('solo considera actividades Finalizadas del período', function () {
    actuarComoAdminDeTenant();
    actividadConAsistencia([10 => 'Presente']);
    actividadConAsistencia([10 => 'Presente'], ['fecha_inicio' => '2026-10-10', 'fecha_fin' => '2026-10-30']);

    ($this->seguimiento)()->assertOk()->assertJsonPath('meta.total', 1);
    ($this->seguimiento)(['fecha_desde' => '2026-05-01', 'fecha_hasta' => '2026-12-31'])->assertOk()->assertJsonPath('meta.total', 0);
});

it('con filtro de departamento solo cuenta a sus participantes', function () {
    actuarComoAdminDeTenant();
    $a = actividadConAsistencia([10 => 'Presente', 11 => 'Presente', 12 => 'Presente', 13 => 'Presente']);
    seguimientoDe($a['actividad'], 10, 30);
    seguimientoDe($a['actividad'], 12, 30);

    $r = ($this->seguimiento)(['departamento_id' => 2])->assertOk();

    expect($r->json('data.0.elegibles'))->toBe(2)->and($r->json('data.0.hitos.0.respuestas'))->toBe(1)->and($r->json('data.0.hitos.0.tasa_respuesta'))->toEqual(50);
});

it('filtra por tipo y plan y pagina', function () {
    actuarComoAdminDeTenant();
    $a = actividadConAsistencia([10 => 'Presente'], ['nombre' => 'Presencial']);
    actividadConAsistencia([10 => 'Presente'], ['nombre' => 'Virtual', 'tipo' => 'Virtual']);

    expect(array_column(($this->seguimiento)(['tipo' => 'Virtual'])->assertOk()->json('data'), 'nombre'))->toBe(['Virtual'])
        ->and(array_column(($this->seguimiento)(['plan_id' => $a['plan']])->assertOk()->json('data'), 'nombre'))->toBe(['Presencial']);
    ($this->seguimiento)(['per_page' => 1, 'page' => 2])->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 2);
});

it('exige rol de administrador', function () {
    ($this->seguimiento)()->assertUnauthorized();
    actuarComoUsuarioDeTenant();
    ($this->seguimiento)()->assertForbidden();
});
