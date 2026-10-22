<?php

use App\Modules\Compensaciones\Infrastructure\Persistence\Eloquent\Models\CategoriaReconocimientoModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;

it('rechaza con 401 sin sesión', function () {
    $this->getJson('/api/compensaciones/categorias')->assertUnauthorized();
});

it('lista para un Usuario solo las activas, aunque pida todas', function () {
    CategoriaReconocimientoModel::factory()->create(['nombre' => 'Activa uno']);
    CategoriaReconocimientoModel::factory()->inactiva()->create(['nombre' => 'Inactiva uno']);
    actuarComoUsuario();

    $this->getJson('/api/compensaciones/categorias?estado=Todos')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.nombre', 'Activa uno')
        ->assertJsonPath('data.0.estado', 'Activo');

    $this->getJson('/api/compensaciones/categorias?estado=Inactivo')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.nombre', 'Activa uno');
});

it('muestra al Administrador las activas por defecto y filtra por estado', function () {
    CategoriaReconocimientoModel::factory()->create(['nombre' => 'Activa uno']);
    CategoriaReconocimientoModel::factory()->inactiva()->create(['nombre' => 'Inactiva uno']);
    actuarComoAdmin();

    $this->getJson('/api/compensaciones/categorias')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/compensaciones/categorias?estado=Todos')->assertOk()->assertJsonCount(2, 'data');
    $this->getJson('/api/compensaciones/categorias?estado=Inactivo')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.nombre', 'Inactiva uno');
});

it('devuelve los datos del listado sin indicadores y con la competencia resuelta', function () {
    $competencia = CompetenciaModel::factory()->create(['nombre' => 'Colaboración']);
    CategoriaReconocimientoModel::factory()->create(['competencia_id' => $competencia->id, 'icono' => 'equipo', 'puntuacion_base' => 120]);
    actuarComoUsuario();

    $this->getJson('/api/compensaciones/categorias')
        ->assertOk()
        ->assertJsonPath('data.0.competencia.nombre', 'Colaboración')
        ->assertJsonPath('data.0.competencia_activa', true)
        ->assertJsonPath('data.0.seleccionable', true)
        ->assertJsonPath('data.0.puntuacion_base', 120)
        ->assertJsonPath('data.0.icono', 'equipo')
        ->assertJsonPath('meta.per_page', 15)
        ->assertJsonMissingPath('data.0.indicadores')
        ->assertJsonMissingPath('data.0.created_by');
});

it('marca la competencia inactiva y deja la categoría no seleccionable', function () {
    CategoriaReconocimientoModel::factory()->conCompetenciaInactiva()->create();
    actuarComoUsuario();

    $this->getJson('/api/compensaciones/categorias')
        ->assertOk()
        ->assertJsonPath('data.0.competencia_activa', false)
        ->assertJsonPath('data.0.seleccionable', false)
        ->assertJsonPath('data.0.estado', 'Activo');
});

it('busca por nombre de categoría sin distinguir mayúsculas ni tildes', function () {
    CategoriaReconocimientoModel::factory()->create(['nombre' => 'Orientación al cliente']);
    CategoriaReconocimientoModel::factory()->create(['nombre' => 'Innovación']);
    actuarComoUsuario();

    $this->getJson('/api/compensaciones/categorias?q=ORIENTACION')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.nombre', 'Orientación al cliente');

    $this->getJson('/api/compensaciones/categorias?q=innovacion')->assertOk()->assertJsonCount(1, 'data');
});

it('busca por nombre de competencia', function () {
    $liderazgo = CompetenciaModel::factory()->create(['nombre' => 'Liderazgo estratégico']);
    $otra = CompetenciaModel::factory()->create(['nombre' => 'Orientación a resultados']);
    CategoriaReconocimientoModel::factory()->create(['nombre' => 'Primera', 'competencia_id' => $liderazgo->id]);
    CategoriaReconocimientoModel::factory()->create(['nombre' => 'Segunda', 'competencia_id' => $otra->id]);
    actuarComoUsuario();

    $this->getJson('/api/compensaciones/categorias?q=LIDERAZGO')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.nombre', 'Primera');
});

it('no interpreta los comodines de LIKE en la búsqueda', function () {
    CategoriaReconocimientoModel::factory()->create(['nombre' => 'Cien por ciento']);
    CategoriaReconocimientoModel::factory()->create(['nombre' => 'Descuento 100%']);
    actuarComoUsuario();

    $this->getJson('/api/compensaciones/categorias?q=%25')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.nombre', 'Descuento 100%');
});

it('filtra por competencia', function () {
    $a = CompetenciaModel::factory()->create();
    $b = CompetenciaModel::factory()->create();
    CategoriaReconocimientoModel::factory()->create(['competencia_id' => $a->id, 'nombre' => 'De A']);
    CategoriaReconocimientoModel::factory()->create(['competencia_id' => $b->id, 'nombre' => 'De B']);
    actuarComoUsuario();

    $this->getJson("/api/compensaciones/categorias?competencia_id={$b->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.nombre', 'De B');
});

it('pagina de 15 en 15 y muestra la última página si se pide una fuera de rango', function () {
    CategoriaReconocimientoModel::factory()->count(17)->conIndicadores(1)->create();
    actuarComoUsuario();

    $this->getJson('/api/compensaciones/categorias')
        ->assertOk()
        ->assertJsonCount(15, 'data')
        ->assertJsonPath('meta.total', 17)
        ->assertJsonPath('meta.last_page', 2);

    $this->getJson('/api/compensaciones/categorias?page=2')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.page', 2);

    $this->getJson('/api/compensaciones/categorias?page=99')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.page', 2);
});

it('ordena por código de forma numérica', function () {
    CategoriaReconocimientoModel::factory()->create(['codigo' => 'CAT-1000', 'nombre' => 'Mil']);
    CategoriaReconocimientoModel::factory()->create(['codigo' => 'CAT-999', 'nombre' => 'Novecientos']);
    CategoriaReconocimientoModel::factory()->create(['codigo' => 'CAT-010', 'nombre' => 'Diez']);
    actuarComoUsuario();

    $this->getJson('/api/compensaciones/categorias')
        ->assertOk()
        ->assertJsonPath('data.*.codigo', ['CAT-010', 'CAT-999', 'CAT-1000']);
});

it('responde 422 ante parámetros inválidos', function (string $consulta) {
    actuarComoUsuario();

    $this->getJson("/api/compensaciones/categorias?{$consulta}")->assertUnprocessable();
})->with([
    'q de más de 100 caracteres' => fn () => 'q='.str_repeat('a', 101),
    'estado desconocido' => 'estado=Raro',
    'página cero' => 'page=0',
    'competencia no numérica' => 'competencia_id=abc',
]);
