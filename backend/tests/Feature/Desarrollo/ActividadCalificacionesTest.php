<?php

beforeEach(function () {
    // Hoy = 2026-03-05.
    $this->falsos = actividadFalsos();
    $this->admin = actuarComoAdminDeTenant();
    $this->nueva = function (string $nombre, array $accion = [], string $lugar = 'Sala 1', ?int $departamento = null) {
        [$plan, $accionId] = accionEnPlan(['nombre' => $nombre, 'colaborador_ids' => [10, 11, 12]] + $accion);
        $a = actividadDe($accionId, $lugar, $departamento);

        return [$a['id'], $plan];
    };
    $this->por = fn (string $consulta) => $this->getJson('/api/actividades/calificaciones?'.$consulta)->assertOk();
});

it('agrupa por actividad con calificados y promedio, y deja sin valor las que no tienen notas', function () {
    [$a1] = ($this->nueva)('Con notas', ['fecha_inicio' => '2026-02-02', 'fecha_fin' => '2026-02-03']);
    ($this->nueva)('Sin notas', ['fecha_inicio' => '2026-02-10', 'fecha_fin' => '2026-02-11']);
    registroDeAsistencia($a1, 10, 'Presente', 3);
    registroDeAsistencia($a1, 11, 'Presente', 4);
    registroDeAsistencia($a1, 12, 'Presente', 5);

    $r = ($this->por)('agrupar=actividad');

    expect($r->json('data'))->toBe([
        ['id' => $r->json('data.0.id'), 'codigo' => 'ACT-0002', 'nombre' => 'Sin notas', 'tipo' => 'Presencial', 'fecha_inicio' => '2026-02-10', 'fecha_fin' => '2026-02-11', 'calificados' => 0, 'promedio' => null],
        ['id' => $a1, 'codigo' => 'ACT-0001', 'nombre' => 'Con notas', 'tipo' => 'Presencial', 'fecha_inicio' => '2026-02-02', 'fecha_fin' => '2026-02-03', 'calificados' => 3, 'promedio' => 4],
    ])->and($r->json('meta.total'))->toBe(2);
});

it('el promedio ignora a quienes asistieron sin calificación y a los ausentes', function () {
    [$a] = ($this->nueva)('Mixta');
    registroDeAsistencia($a, 10, 'Presente', 5);
    registroDeAsistencia($a, 11, 'Presente');
    registroDeAsistencia($a, 12, 'Ausente');

    ($this->por)('agrupar=actividad')->assertJsonPath('data.0.calificados', 1)->assertJsonPath('data.0.promedio', 5);
});

it('agrupa por participante solo con quienes tienen al menos una calificación', function () {
    [$a1] = ($this->nueva)('Una');
    [$a2] = ($this->nueva)('Dos');
    registroDeAsistencia($a1, 10, 'Presente', 4);
    registroDeAsistencia($a2, 10, 'Presente', 5);
    registroDeAsistencia($a1, 11, 'Presente', 3);
    registroDeAsistencia($a1, 12, 'Presente');
    registroDeAsistencia($a2, 12, 'Ausente');

    $r = ($this->por)('agrupar=participante');

    expect($r->json('data'))->toBe([
        ['colaborador_id' => 10, 'nombre' => 'Colaborador 10', 'actividades_calificadas' => 2, 'promedio' => 4.5],
        ['colaborador_id' => 11, 'nombre' => 'Colaborador 11', 'actividades_calificadas' => 1, 'promedio' => 3],
    ]);
});

it('el promedio por participante considera solo las actividades del filtro', function () {
    [$a1] = ($this->nueva)('Presencial vieja', ['fecha_inicio' => '2026-01-10', 'fecha_fin' => '2026-01-11']);
    [$a2] = ($this->nueva)('Virtual nueva', ['tipo' => 'Virtual', 'fecha_inicio' => '2026-02-10', 'fecha_fin' => '2026-02-11'], 'https://meet.test/x');
    registroDeAsistencia($a1, 10, 'Presente', 2);
    registroDeAsistencia($a2, 10, 'Presente', 5);

    ($this->por)('agrupar=participante')->assertJsonPath('data.0.promedio', 3.5)->assertJsonPath('data.0.actividades_calificadas', 2);
    ($this->por)('agrupar=participante&tipo=Virtual')->assertJsonPath('data.0.promedio', 5)->assertJsonPath('data.0.actividades_calificadas', 1);
    ($this->por)('agrupar=participante&fecha_hasta=2026-01-31')->assertJsonPath('data.0.promedio', 2);
    ($this->por)('agrupar=participante&fecha_desde=2026-03-01')->assertJsonPath('data', []);
});

it('filtra por departamento y por plan', function () {
    [$a1, $plan1] = ($this->nueva)('Tecnología', [], 'Sala 1', 1);
    [$a2] = ($this->nueva)('Talento', [], 'Sala 2', 2);
    registroDeAsistencia($a1, 10, 'Presente', 5);
    registroDeAsistencia($a2, 10, 'Presente', 1);

    ($this->por)('agrupar=participante&departamento_id=1')->assertJsonPath('data.0.promedio', 5);
    ($this->por)('agrupar=actividad&departamento_id=2')->assertJsonCount(1, 'data')->assertJsonPath('data.0.nombre', 'Talento');
    ($this->por)("agrupar=participante&plan_id={$plan1}")->assertJsonPath('data.0.promedio', 5);
});

it('devuelve el detalle por actividad de un participante', function () {
    [$a1] = ($this->nueva)('Una', ['fecha_inicio' => '2026-01-10', 'fecha_fin' => '2026-01-11']);
    [$a2] = ($this->nueva)('Dos', ['fecha_inicio' => '2026-02-10', 'fecha_fin' => '2026-02-11']);
    [$a3] = ($this->nueva)('Tres');
    registroDeAsistencia($a1, 10, 'Presente', 4);
    registroDeAsistencia($a2, 10, 'Ausente');
    registroDeAsistencia($a3, 11, 'Presente', 5);

    $r = ($this->por)('agrupar=participante&colaborador_id=10');

    expect($r->json('data.colaborador_id'))->toBe(10)
        ->and($r->json('data.nombre'))->toBe('Colaborador 10')
        ->and($r->json('data.promedio'))->toBe(4)
        ->and($r->json('data.actividades_calificadas'))->toBe(1)
        ->and($r->json('data.actividades'))->toBe([
            ['id' => $a2, 'codigo' => 'ACT-0002', 'nombre' => 'Dos', 'tipo' => 'Presencial', 'fecha_inicio' => '2026-02-10', 'asistencia' => 'Ausente', 'calificacion' => null],
            ['id' => $a1, 'codigo' => 'ACT-0001', 'nombre' => 'Una', 'tipo' => 'Presencial', 'fecha_inicio' => '2026-01-10', 'asistencia' => 'Presente', 'calificacion' => 4],
        ]);
});

it('pagina y nunca incluye observaciones', function () {
    foreach (range(1, 3) as $i) {
        [$a] = ($this->nueva)("Actividad {$i}", ['fecha_inicio' => '2026-02-0'.$i, 'fecha_fin' => '2026-02-1'.$i]);
        registroDeAsistencia($a, 10, 'Presente', 4, 'Confidencial');
    }

    $r = ($this->por)('agrupar=actividad&per_page=2&page=2');

    expect($r->json('meta'))->toBe(['page' => 2, 'per_page' => 2, 'total' => 3, 'last_page' => 2])
        ->and($r->getContent())->not->toContain('observaciones')->not->toContain('Confidencial')
        ->and(($this->por)('agrupar=participante&colaborador_id=10')->getContent())->not->toContain('Confidencial');
});

it('valida los parámetros', function (string $consulta) {
    $this->getJson('/api/actividades/calificaciones?'.$consulta)->assertUnprocessable();
})->with([
    'sin agrupar' => [''],
    'agrupar inválido' => ['agrupar=curso'],
    'colaborador al agrupar por actividad' => ['agrupar=actividad&colaborador_id=10'],
    'tipo inválido' => ['agrupar=actividad&tipo=Otro'],
    'rango invertido' => ['agrupar=actividad&fecha_desde=2026-05-01&fecha_hasta=2026-04-01'],
]);

it('exige administrador y aísla por tenant', function () {
    [$a] = ($this->nueva)('Una');
    registroDeAsistencia($a, 10, 'Presente', 4);
    actuarComoAdminDeTenant(2);
    ($this->por)('agrupar=actividad')->assertJsonPath('data', []);

    actuarComoUsuarioDeTenant();
    $this->getJson('/api/actividades/calificaciones?agrupar=actividad')->assertForbidden();
    app('auth')->forgetGuards();
    $this->getJson('/api/actividades/calificaciones?agrupar=actividad')->assertUnauthorized();
});
