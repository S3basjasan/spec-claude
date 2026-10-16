<?php

use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\CursoModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\MicrocontenidoModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;

it('exige el tipo y rechaza valores inválidos', function () {
    actuarComoAdmin();

    $this->getJson('/api/catalogo-formativo/recursos-disponibles')->assertUnprocessable()->assertJsonValidationErrors('tipo');
    $this->getJson('/api/catalogo-formativo/recursos-disponibles?tipo=otro')->assertUnprocessable()->assertJsonValidationErrors('tipo');
});

it('lista solo cursos disponibles, ordenados por nombre, con el número de competencias que los usan', function () {
    $usado = CursoModel::factory()->create(['nombre' => 'B usado']);
    CursoModel::factory()->create(['nombre' => 'A libre']);
    CursoModel::factory()->archivado()->create(['nombre' => 'C archivado']);
    configurarFormativa(CompetenciaModel::factory()->create()->id, null, [$usado->id]);
    configurarFormativa(CompetenciaModel::factory()->create()->id, null, [$usado->id]);
    actuarComoAdmin();

    $datos = $this->getJson('/api/catalogo-formativo/recursos-disponibles?tipo=curso')->assertOk()->json('data');

    expect(array_column($datos, 'nombre'))->toBe(['A libre', 'B usado'])
        ->and(array_column($datos, 'competencias_count'))->toBe([0, 2]);
});

it('excluye los ya vinculados a la competencia indicada y busca sin tildes', function () {
    $competencia = CompetenciaModel::factory()->create();
    $vinculado = CursoModel::factory()->create(['nombre' => 'Gestión vinculada']);
    CursoModel::factory()->create(['nombre' => 'Gestión libre']);
    CursoModel::factory()->create(['nombre' => 'Otro tema']);
    configurarFormativa($competencia->id, null, [$vinculado->id]);
    actuarComoAdmin();

    $nombres = array_column(
        $this->getJson("/api/catalogo-formativo/recursos-disponibles?tipo=curso&competencia_id={$competencia->id}&q=GESTION")->json('data'),
        'nombre',
    );

    expect($nombres)->toBe(['Gestión libre']);
});

it('sin micro-contenidos registrados devuelve una lista vacía y lista los existentes disponibles', function () {
    actuarComoAdmin();
    $this->getJson('/api/catalogo-formativo/recursos-disponibles?tipo=microcontenido')->assertOk()->assertJsonPath('data', []);

    MicrocontenidoModel::factory()->create(['nombre' => 'Video uno']);
    MicrocontenidoModel::factory()->archivado()->create();

    expect($this->getJson('/api/catalogo-formativo/recursos-disponibles?tipo=microcontenido')->json('data.0.accion'))->toBe('Ver');
});

it('el colaborador recibe 403 y sin sesión 401', function () {
    $this->getJson('/api/catalogo-formativo/recursos-disponibles?tipo=curso')->assertUnauthorized();

    actuarComoUsuario();
    $this->getJson('/api/catalogo-formativo/recursos-disponibles?tipo=curso')->assertForbidden();
});
