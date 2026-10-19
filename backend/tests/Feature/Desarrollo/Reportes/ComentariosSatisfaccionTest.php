<?php

beforeEach(function () {
    $this->falsos = actividadFalsos(['hoy' => '2026-10-17', 'vigentes' => [10 => 'Ana', 11 => 'Luis', 12 => 'Eva']]);
    departamentosActuales([10 => 1, 11 => 1, 12 => 2]);
    $this->comentarios = fn (int $id, array $q = []) => $this->getJson("/api/reportes/actividades/$id/satisfaccion?".http_build_query($q));
});

it('devuelve promedio, respuestas y comentarios sin ningún dato del autor', function () {
    actuarComoAdminDeTenant();
    $a = actividadConAsistencia([10 => 'Presente', 11 => 'Presente', 12 => 'Presente']);
    encuestaDe($a['actividad'], 10, 5, 'Excelente curso', '2026-03-15');
    encuestaDe($a['actividad'], 11, 3, 'Muy largo', '2026-03-16');
    encuestaDe($a['actividad'], 12, 4);

    $r = ($this->comentarios)($a['actividad'])->assertOk()
        ->assertJsonPath('data.promedio', 4)
        ->assertJsonPath('data.respuestas', 3)
        ->assertJsonPath('meta.total', 2);

    $comentarios = $r->json('data.comentarios');
    expect($comentarios)->toHaveCount(2)
        ->and($comentarios[0])->toBe(['calificacion' => 3, 'comentario' => 'Muy largo', 'fecha' => '2026-03-16'])
        ->and($comentarios[1])->toBe(['calificacion' => 5, 'comentario' => 'Excelente curso', 'fecha' => '2026-03-15']);

    foreach ($comentarios as $c) {
        expect(array_keys($c))->toBe(['calificacion', 'comentario', 'fecha']);
    }
    $texto = $r->getContent();
    expect($texto)->not->toContain('colaborador')->not->toContain('Ana')->not->toContain('Luis')->not->toContain('"10"');
});

it('muestra los comentarios aunque haya una sola respuesta', function () {
    actuarComoAdminDeTenant();
    $a = actividadConAsistencia([10 => 'Presente']);
    encuestaDe($a['actividad'], 10, 2, 'Pocos ejemplos');

    ($this->comentarios)($a['actividad'])->assertOk()->assertJsonPath('data.respuestas', 1)->assertJsonCount(1, 'data.comentarios');
});

it('una actividad sin respuestas devuelve promedio nulo y lista vacía', function () {
    actuarComoAdminDeTenant();
    $a = actividadConAsistencia([10 => 'Presente']);

    ($this->comentarios)($a['actividad'])->assertOk()
        ->assertJsonPath('data.promedio', null)->assertJsonPath('data.respuestas', 0)->assertJsonPath('data.comentarios', []);
});

it('pagina los comentarios', function () {
    actuarComoAdminDeTenant();
    $a = actividadConAsistencia([10 => 'Presente', 11 => 'Presente', 12 => 'Presente']);
    foreach ([10, 11, 12] as $c) {
        encuestaDe($a['actividad'], $c, 4, "Comentario $c");
    }

    ($this->comentarios)($a['actividad'], ['per_page' => 2, 'page' => 2])->assertOk()
        ->assertJsonCount(1, 'data.comentarios')->assertJsonPath('meta.total', 3)->assertJsonPath('meta.page', 2);
});

it('responde 404 con una actividad inexistente', function () {
    actuarComoAdminDeTenant();

    ($this->comentarios)(9999)->assertNotFound()->assertJsonPath('code', 'actividad_no_encontrada');
});

it('exige rol de administrador', function () {
    ($this->comentarios)(1)->assertUnauthorized();
    actuarComoUsuarioDeTenant();
    ($this->comentarios)(1)->assertForbidden();
});

it('no entrega los comentarios de otro tenant', function () {
    actuarComoAdminDeTenant();
    $a = actividadConAsistencia([10 => 'Presente']);
    encuestaDe($a['actividad'], 99, 5, 'De otro tenant', tenantId: 2);

    ($this->comentarios)($a['actividad'])->assertOk()->assertJsonPath('data.respuestas', 0)->assertJsonPath('data.comentarios', []);
});
