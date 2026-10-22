<?php

beforeEach(function () {
    rutasFalsos();
    actuarComoAdminDeTenant();
    $this->a = rutaEnBd([1, 2, 3], 'Vigente', ['nombre' => 'Ruta de datos', 'tipo' => 'Especialista', 'departamento_id' => 1]);
    $this->b = rutaEnBd([4, 5], 'Borrador', ['nombre' => 'Ruta gerencial', 'tipo' => 'Gerencial', 'departamento_id' => 2]);
    $this->c = rutaEnBd([1, 2], 'Archivada', ['nombre' => 'Ruta interáreas', 'tipo' => 'Interareas', 'departamento_id' => null]);
    inscripcionEnBd($this->a['id'], 10, $this->a['niveles'][0], $this->a['niveles'][1]);
    inscripcionEnBd($this->a['id'], 11);
    inscripcionEnBd($this->a['id'], 12, estado: 'Retirada', motivo: 'Cambio');
});

it('lista todas las rutas con código, estado, niveles e inscritos activos', function () {
    $r = $this->getJson('/api/rutas')->assertOk();

    expect($r->json('meta'))->toBe(['page' => 1, 'per_page' => 50, 'total' => 3, 'last_page' => 1])
        ->and(array_column($r->json('data'), 'codigo'))->toBe(['CAR-0001', 'CAR-0002', 'CAR-0003'])
        ->and($r->json('data.0'))->toMatchArray(['nombre' => 'Ruta de datos', 'tipo' => 'Especialista', 'estado' => 'Vigente', 'niveles_count' => 3, 'inscritos_count' => 2, 'departamento' => 'Tecnología'])
        ->and($r->json('data.1'))->toMatchArray(['estado' => 'Borrador', 'niveles_count' => 2, 'inscritos_count' => 0, 'departamento' => 'Talento Humano'])
        ->and($r->json('data.2.departamento'))->toBeNull();
});

it('filtra por tipo, estado, departamento y nombre', function () {
    $nombres = fn (string $query) => array_column($this->getJson("/api/rutas?{$query}")->assertOk()->json('data'), 'nombre');

    expect($nombres('tipo=Gerencial'))->toBe(['Ruta gerencial'])
        ->and($nombres('estado=Archivada'))->toBe(['Ruta interáreas'])
        ->and($nombres('departamento_id=1'))->toBe(['Ruta de datos'])
        ->and($nombres('q='.urlencode('GEREN')))->toBe(['Ruta gerencial'])
        ->and($nombres('q=inexistente'))->toBe([])
        ->and($nombres('tipo=Especialista&estado=Borrador'))->toBe([]);
});

it('pagina el listado', function () {
    $r = $this->getJson('/api/rutas?per_page=2&page=2')->assertOk();

    expect($r->json('meta'))->toBe(['page' => 2, 'per_page' => 2, 'total' => 3, 'last_page' => 2])->and($r->json('data'))->toHaveCount(1);
});

it('valida los filtros', function () {
    $this->getJson('/api/rutas?tipo=Inventado')->assertUnprocessable()->assertJsonValidationErrors('tipo');
    $this->getJson('/api/rutas?estado=Cerrada')->assertUnprocessable()->assertJsonValidationErrors('estado');
    $this->getJson('/api/rutas?per_page=500')->assertUnprocessable()->assertJsonValidationErrors('per_page');
});

it('no mezcla rutas de otro tenant', function () {
    rutaEnBd([1, 2], 'Vigente', ['nombre' => 'Ruta ajena'], tenantId: 2);

    expect($this->getJson('/api/rutas')->assertOk()->json('meta.total'))->toBe(3);
});

it('el colaborador no accede a la lista de gestión', function () {
    actuarComoUsuarioDeTenant();

    $this->getJson('/api/rutas')->assertForbidden();
});
