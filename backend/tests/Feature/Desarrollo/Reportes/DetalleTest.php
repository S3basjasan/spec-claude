<?php

beforeEach(function () {
    $this->falsos = actividadFalsos(['hoy' => '2026-10-17', 'vigentes' => [10 => 'Ana', 11 => 'Luis', 12 => 'Eva', 13 => 'Pedro']]);
    departamentosActuales([10 => 1, 11 => 1, 12 => 2, 13 => 2]);
    $this->detalle = fn (array $filtros = []) => $this->getJson('/api/reportes/detalle?'.http_build_query($filtros));
});

it('lista las actividades En curso o Finalizadas del período, no las Programadas', function () {
    actuarComoAdminDeTenant();
    $finalizada = actividadConAsistencia([10 => 'Presente', 11 => 'Presente'], ['nombre' => 'Finalizada']);
    $enCurso = actividadConAsistencia([10 => 'Presente'], ['nombre' => 'En curso', 'fecha_inicio' => '2026-10-10', 'fecha_fin' => '2026-10-30']);
    actividadConAsistencia([10 => 'Presente'], ['nombre' => 'Programada', 'fecha_inicio' => '2026-12-01', 'fecha_fin' => '2026-12-10']);

    $r = ($this->detalle)()->assertOk()
        ->assertJsonPath('meta.total', 2)->assertJsonPath('meta.page', 1)->assertJsonPath('meta.per_page', 20);

    expect(array_column($r->json('data'), 'nombre'))->toBe(['En curso', 'Finalizada'])
        ->and($r->json('data.1.actividad_id'))->toBe($finalizada['actividad'])
        ->and($r->json('data.0.actividad_id'))->toBe($enCurso['actividad']);
});

it('entrega las columnas del contrato', function () {
    actuarComoAdminDeTenant();
    $a = actividadConAsistencia([10 => 'Presente', 11 => 'Presente', 12 => 'Ausente'], [], [10 => 5, 11 => 3], departamentoId: 2);
    encuestaDe($a['actividad'], 10, 4);
    encuestaDe($a['actividad'], 11, 5);

    ($this->detalle)()->assertOk()
        ->assertJsonPath('data.0.codigo', 'ACT-0001')
        ->assertJsonPath('data.0.tipo', 'Presencial')
        ->assertJsonPath('data.0.estado', 'Finalizada')
        ->assertJsonPath('data.0.fecha_inicio', '2026-03-02')
        ->assertJsonPath('data.0.fecha_fin', '2026-03-13')
        ->assertJsonPath('data.0.departamento_id', 2)
        ->assertJsonPath('data.0.departamento', 'Talento Humano')
        ->assertJsonPath('data.0.asistentes', 2)
        ->assertJsonPath('data.0.inscritos', 3)
        ->assertJsonPath('data.0.horas_persona', 32)
        ->assertJsonPath('data.0.costo', 800)
        ->assertJsonPath('data.0.calificacion_promedio', 4)
        ->assertJsonPath('data.0.satisfaccion_promedio', 4.5)
        ->assertJsonPath('data.0.respuestas_satisfaccion', 2);
});

it('con filtro de departamento recalcula asistentes, horas y satisfacción y prorratea el costo', function () {
    actuarComoAdminDeTenant();
    $a = actividadConAsistencia([10 => 'Presente', 11 => 'Presente', 12 => 'Presente', 13 => 'Ausente'], [], [10 => 5, 12 => 3]);
    encuestaDe($a['actividad'], 10, 5);
    encuestaDe($a['actividad'], 12, 2);

    ($this->detalle)(['departamento_id' => 2])->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.asistentes', 1)
        ->assertJsonPath('data.0.inscritos', 2)
        ->assertJsonPath('data.0.horas_persona', 16)
        ->assertJsonPath('data.0.costo', 266.67)
        ->assertJsonPath('data.0.calificacion_promedio', 3)
        ->assertJsonPath('data.0.satisfaccion_promedio', 2)
        ->assertJsonPath('data.0.respuestas_satisfaccion', 1);
});

it('un departamento sin participantes de la actividad no la muestra', function () {
    actuarComoAdminDeTenant();
    actividadConAsistencia([10 => 'Presente', 11 => 'Presente']);

    ($this->detalle)(['departamento_id' => 2])->assertOk()->assertJsonPath('meta.total', 0)->assertJsonPath('data', []);
});

it('filtra por tipo, plan y período', function () {
    actuarComoAdminDeTenant();
    $uno = actividadConAsistencia([10 => 'Presente'], ['nombre' => 'Presencial']);
    actividadConAsistencia([10 => 'Presente'], ['nombre' => 'Virtual', 'tipo' => 'Virtual']);

    expect(array_column(($this->detalle)(['tipo' => 'Virtual'])->assertOk()->json('data'), 'nombre'))->toBe(['Virtual'])
        ->and(array_column(($this->detalle)(['plan_id' => $uno['plan']])->assertOk()->json('data'), 'nombre'))->toBe(['Presencial'])
        ->and(($this->detalle)(['fecha_desde' => '2026-04-01', 'fecha_hasta' => '2026-12-31'])->assertOk()->json('meta.total'))->toBe(0);
});

it('busca por nombre o código y pagina', function () {
    actuarComoAdminDeTenant();
    foreach (['Excel avanzado', 'Liderazgo', 'Excel básico'] as $nombre) {
        actividadConAsistencia([10 => 'Presente'], ['nombre' => $nombre]);
    }

    expect(array_column(($this->detalle)(['q' => 'excel'])->assertOk()->json('data'), 'nombre'))->toHaveCount(2)
        ->and(($this->detalle)(['q' => 'ACT-0002'])->assertOk()->json('data.0.nombre'))->toBe('Liderazgo');

    ($this->detalle)(['per_page' => 2, 'page' => 2])->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 3)->assertJsonPath('meta.page', 2);
});

it('la satisfacción sin encuestas no tiene valor', function () {
    actuarComoAdminDeTenant();
    actividadConAsistencia([10 => 'Presente']);

    ($this->detalle)()->assertOk()->assertJsonPath('data.0.satisfaccion_promedio', null)->assertJsonPath('data.0.respuestas_satisfaccion', 0);
});

it('exige rol de administrador', function () {
    ($this->detalle)()->assertUnauthorized();
    actuarComoUsuarioDeTenant();
    ($this->detalle)()->assertForbidden();
});
