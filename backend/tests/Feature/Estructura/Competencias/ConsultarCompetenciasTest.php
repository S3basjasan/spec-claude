<?php

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;

it('rechaza con 401 sin sesión', function () {
    $this->getJson('/api/competencias')->assertUnauthorized();
    $this->getJson('/api/competencias/1')->assertUnauthorized();
});

it('lista para un Usuario ordenado por nombre, con código y estado y sin grados', function () {
    CompetenciaModel::factory()->create(['nombre' => 'Zeta']);
    CompetenciaModel::factory()->create(['nombre' => 'alfa']);
    CompetenciaModel::factory()->create(['nombre' => 'Beta']);
    actuarComoUsuario();

    $respuesta = $this->getJson('/api/competencias');

    $respuesta->assertOk()
        ->assertJsonPath('data.*.nombre', ['alfa', 'Beta', 'Zeta'])
        ->assertJsonPath('data.0.codigo', 'COMP-0002')
        ->assertJsonPath('data.0.estado', 'Activo')
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('meta.per_page', 10)
        ->assertJsonMissingPath('data.0.grados')
        ->assertJsonStructure(['data' => [['id', 'codigo', 'nombre', 'descripcion', 'tipo', 'estado']], 'links', 'meta']);
});

it('lista también para un Administrador', function () {
    CompetenciaModel::factory()->create();
    actuarComoAdmin();

    $this->getJson('/api/competencias')->assertOk()->assertJsonPath('meta.total', 1);
});

it('al Usuario solo le llegan las activas aunque pida otro estado', function () {
    CompetenciaModel::factory()->create(['nombre' => 'Vigente']);
    CompetenciaModel::factory()->inactiva()->create(['nombre' => 'Retirada']);
    actuarComoUsuario();

    foreach (['', '?estado=Todos', '?estado=Inactivo', '?estado=Activo'] as $consulta) {
        $this->getJson("/api/competencias$consulta")->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.nombre', 'Vigente');
    }
});

it('al Administrador le llegan las activas por defecto y puede pedir inactivas o todas', function () {
    CompetenciaModel::factory()->create(['nombre' => 'Vigente']);
    CompetenciaModel::factory()->inactiva()->create(['nombre' => 'Retirada']);
    actuarComoAdmin();

    $this->getJson('/api/competencias')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.nombre', 'Vigente');
    $this->getJson('/api/competencias?estado=Activo')->assertOk()->assertJsonPath('meta.total', 1);
    $this->getJson('/api/competencias?estado=Inactivo')->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.nombre', 'Retirada')
        ->assertJsonPath('data.0.estado', 'Inactivo');
    $this->getJson('/api/competencias?estado=Todos')->assertOk()->assertJsonPath('meta.total', 2);
});

it('busca por nombre sin distinguir mayúsculas y filtra por tipo, combinados', function () {
    CompetenciaModel::factory()->cardinal()->create(['nombre' => 'Trabajo en equipo']);
    CompetenciaModel::factory()->especifica()->create(['nombre' => 'Trabajo remoto']);
    CompetenciaModel::factory()->cardinal()->create(['nombre' => 'Liderazgo']);
    actuarComoUsuario();

    $this->getJson('/api/competencias?q=TRABAJO')->assertOk()->assertJsonPath('meta.total', 2);
    $this->getJson('/api/competencias?tipo=Cardinal')->assertOk()->assertJsonPath('meta.total', 2);
    $this->getJson('/api/competencias?q=trabajo&tipo=Cardinal')->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.nombre', 'Trabajo en equipo');
});

it('combina búsqueda, tipo y estado para el Administrador', function () {
    CompetenciaModel::factory()->cardinal()->create(['nombre' => 'Trabajo en equipo']);
    CompetenciaModel::factory()->cardinal()->inactiva()->create(['nombre' => 'Trabajo en red']);
    CompetenciaModel::factory()->especifica()->inactiva()->create(['nombre' => 'Trabajo remoto']);
    actuarComoAdmin();

    $this->getJson('/api/competencias?q=trabajo&tipo=Cardinal&estado=Inactivo')->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.nombre', 'Trabajo en red');
});

it('busca sin distinguir mayúsculas con acentos', function () {
    CompetenciaModel::factory()->create(['nombre' => 'Orientación a resultados']);
    CompetenciaModel::factory()->create(['nombre' => 'Liderazgo']);
    actuarComoUsuario();

    $this->getJson('/api/competencias?q=ORIENTACIÓN')->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.nombre', 'Orientación a resultados');
});

it('no trata % ni _ de la búsqueda como comodines', function () {
    CompetenciaModel::factory()->create(['nombre' => 'Liderazgo']);
    CompetenciaModel::factory()->create(['nombre' => 'Mejora 100% continua']);
    actuarComoUsuario();

    $this->getJson('/api/competencias?q=%25')->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.nombre', 'Mejora 100% continua');
    $this->getJson('/api/competencias?q=_')->assertOk()->assertJsonPath('meta.total', 0);
});

it('pagina con per_page por defecto 10 y respeta page y per_page', function () {
    CompetenciaModel::factory()->count(12)->create();
    actuarComoUsuario();

    $this->getJson('/api/competencias')->assertOk()->assertJsonCount(10, 'data')->assertJsonPath('meta.last_page', 2);
    $this->getJson('/api/competencias?page=2')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.from', 11);
    $this->getJson('/api/competencias?per_page=5&page=3')->assertOk()->assertJsonCount(2, 'data');
});

it('devuelve 422 con parámetros inválidos', function () {
    actuarComoUsuario();

    $this->getJson('/api/competencias?tipo=otra')->assertUnprocessable()->assertJsonValidationErrors('tipo');
    $this->getJson('/api/competencias?tipo=cardinal')->assertUnprocessable()->assertJsonValidationErrors('tipo');
    $this->getJson('/api/competencias?estado=Borrado')->assertUnprocessable()->assertJsonValidationErrors('estado');
    $this->getJson('/api/competencias?per_page=51')->assertUnprocessable()->assertJsonValidationErrors('per_page');
    $this->getJson('/api/competencias?per_page=0')->assertUnprocessable()->assertJsonValidationErrors('per_page');
    $this->getJson('/api/competencias?page=0')->assertUnprocessable()->assertJsonValidationErrors('page');
});

it('devuelve el detalle con estado, versión y los cuatro grados en orden A–D', function () {
    $competencia = CompetenciaModel::factory()->create(['nombre' => 'Liderazgo']);
    actuarComoUsuario();

    $this->getJson("/api/competencias/{$competencia->id}")
        ->assertOk()
        ->assertJsonPath('data.codigo', 'COMP-0001')
        ->assertJsonPath('data.estado', 'Activo')
        ->assertJsonPath('data.version', 1)
        ->assertJsonCount(4, 'data.grados')
        ->assertJsonPath('data.grados.*.grado', ['A', 'B', 'C', 'D']);
});

it('permite a cualquier persona autenticada consultar una competencia inactiva', function () {
    $competencia = CompetenciaModel::factory()->inactiva()->create(['nombre' => 'Retirada']);

    actuarComoUsuario();
    $this->getJson("/api/competencias/{$competencia->id}")
        ->assertOk()
        ->assertJsonPath('data.estado', 'Inactivo')
        ->assertJsonCount(4, 'data.grados');

    actuarComoAdmin();
    $this->getJson("/api/competencias/{$competencia->id}")->assertOk()->assertJsonPath('data.estado', 'Inactivo');
});

it('devuelve 404 con mensaje si no existe', function () {
    actuarComoUsuario();

    $this->getJson('/api/competencias/999')->assertNotFound()->assertExactJson(['message' => 'La competencia no existe.']);
});
