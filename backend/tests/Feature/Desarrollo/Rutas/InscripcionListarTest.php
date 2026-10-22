<?php

beforeEach(function () {
    rutasFalsos();
    actuarComoAdminDeTenant();
    $this->a = rutaEnBd([1, 2, 3], 'Vigente');
    $this->b = rutaEnBd([1, 2], 'Vigente');
    inscripcionEnBd($this->a['id'], 10, $this->a['niveles'][0], $this->a['niveles'][1]);
    inscripcionEnBd($this->a['id'], 11, null, $this->a['niveles'][2]);
    inscripcionEnBd($this->a['id'], 12, $this->a['niveles'][1], null, 'Retirada', motivo: 'Cambio de área');
    inscripcionEnBd($this->b['id'], 10, $this->b['niveles'][0], $this->b['niveles'][1]);
    inscripcionEnBd($this->a['id'], 13, $this->a['niveles'][1], null, 'Finalizada');
    departamentosActuales([10 => 1, 11 => 2]);
});

it('lista las inscripciones con colaborador, departamento, niveles y estado', function () {
    $r = $this->getJson('/api/inscripciones-ruta?ruta_id='.$this->a['id'])->assertOk();

    expect($r->json('meta'))->toBe(['page' => 1, 'per_page' => 20, 'total' => 4, 'last_page' => 1]);
    $porColaborador = collect($r->json('data'))->keyBy('colaborador.id');

    expect($porColaborador[10]['colaborador'])->toBe(['id' => 10, 'nombre' => 'Colaborador 10', 'departamento' => 'Tecnología'])
        ->and($porColaborador[10]['nivel_actual'])->toMatchArray(['orden' => 1, 'nombre' => 'Nivel 1'])
        ->and($porColaborador[10]['nivel_objetivo'])->toMatchArray(['orden' => 2])
        ->and($porColaborador[11]['nivel_actual'])->toBeNull()
        ->and($porColaborador[11]['colaborador']['departamento'])->toBe('Talento Humano')
        ->and($porColaborador[12])->toMatchArray(['estado' => 'Retirada', 'motivo' => 'Cambio de área'])
        ->and($porColaborador[12]['colaborador']['departamento'])->toBeNull()
        ->and($porColaborador[13]['estado'])->toBe('Finalizada')->and($porColaborador[13]['resuelta_at'])->not->toBeNull();
});

it('filtra por estado, colaborador y ruta', function () {
    $idsDe = fn (string $query) => collect($this->getJson("/api/inscripciones-ruta?{$query}")->assertOk()->json('data'))->pluck('colaborador.id')->sort()->values()->all();

    expect($idsDe('ruta_id='.$this->a['id'].'&estado=Activa'))->toBe([10, 11])
        ->and($idsDe('ruta_id='.$this->a['id'].'&estado=Retirada'))->toBe([12])
        ->and($idsDe('colaborador_id=10'))->toBe([10, 10])
        ->and($idsDe('ruta_id='.$this->b['id']))->toBe([10]);
    expect($idsDe('estado=Finalizada'))->toBe([13]);
});

it('pagina y valida los filtros', function () {
    $r = $this->getJson('/api/inscripciones-ruta?per_page=2&page=2')->assertOk();

    expect($r->json('meta'))->toBe(['page' => 2, 'per_page' => 2, 'total' => 5, 'last_page' => 3])->and($r->json('data'))->toHaveCount(2);
    $this->getJson('/api/inscripciones-ruta?estado=Cerrada')->assertUnprocessable()->assertJsonValidationErrors('estado');
    $this->getJson('/api/inscripciones-ruta?per_page=101')->assertUnprocessable()->assertJsonValidationErrors('per_page');
});

it('si Personal no entrega el departamento, la lista sigue respondiendo', function () {
    departamentosActualesCaidos();

    $r = $this->getJson('/api/inscripciones-ruta?ruta_id='.$this->a['id'])->assertOk();

    expect(collect($r->json('data'))->pluck('colaborador.departamento')->filter()->all())->toBe([]);
});

it('no mezcla inscripciones de otro tenant', function () {
    $ajena = rutaEnBd([1, 2], 'Vigente', [], tenantId: 2);
    inscripcionEnBd($ajena['id'], 10, tenantId: 2);

    expect($this->getJson('/api/inscripciones-ruta')->assertOk()->json('meta.total'))->toBe(5);
});

it('busca solo colaboradores con vínculo vigente, con búsqueda y paginación', function () {
    $todos = $this->getJson('/api/inscripciones-ruta/colaboradores')->assertOk();
    expect(array_column($todos->json('data'), 'nombre'))->toBe(['Ana Paredes', 'Luis Mora', 'Marta Ríos', 'Pablo Ayala'])
        ->and($todos->json('meta.total'))->toBe(4);

    expect(array_column($this->getJson('/api/inscripciones-ruta/colaboradores?q=mora')->assertOk()->json('data'), 'id'))->toBe([11]);
    expect($this->getJson('/api/inscripciones-ruta/colaboradores?per_page=2&page=2')->assertOk()->json('data'))->toHaveCount(2);
});

it('solo el administrador lista inscripciones y busca colaboradores', function () {
    actuarComoUsuarioDeTenant();

    $this->getJson('/api/inscripciones-ruta')->assertForbidden();
    $this->getJson('/api/inscripciones-ruta/colaboradores')->assertForbidden();
});
