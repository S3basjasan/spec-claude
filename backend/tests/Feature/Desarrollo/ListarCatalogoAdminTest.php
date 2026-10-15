<?php

use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\AreaFormativaModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\CursoModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\MicrocontenidoModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;

function nombresDeLaMatriz($respuesta): array
{
    return array_column($respuesta->json('data'), 'nombre');
}

it('por defecto solo muestra las activas y permite pedir inactivas o todas', function () {
    CompetenciaModel::factory()->create(['nombre' => 'Activa']);
    CompetenciaModel::factory()->inactiva()->create(['nombre' => 'Inactiva']);
    actuarComoAdmin();

    expect(nombresDeLaMatriz($this->getJson('/api/catalogo-formativo')))->toBe(['Activa'])
        ->and(nombresDeLaMatriz($this->getJson('/api/catalogo-formativo?estado=Inactivo')))->toBe(['Inactiva'])
        ->and(nombresDeLaMatriz($this->getJson('/api/catalogo-formativo?estado=Todos')))->toBe(['Activa', 'Inactiva']);
});

it('filtra por tipo, área, búsqueda sin tildes ni mayúsculas y combina filtros', function () {
    $area = AreaFormativaModel::factory()->create();
    $a = CompetenciaModel::factory()->cardinal()->create(['nombre' => 'Gestión de proyectos']);
    CompetenciaModel::factory()->especifica()->create(['nombre' => 'Análisis']);
    configurarFormativa($a->id, $area->id);
    actuarComoAdmin();

    expect(nombresDeLaMatriz($this->getJson('/api/catalogo-formativo?tipo=Cardinal')))->toBe(['Gestión de proyectos'])
        ->and(nombresDeLaMatriz($this->getJson('/api/catalogo-formativo?area_formativa_id='.$area->id)))->toBe(['Gestión de proyectos'])
        ->and(nombresDeLaMatriz($this->getJson('/api/catalogo-formativo?q=GESTION')))->toBe(['Gestión de proyectos'])
        ->and(nombresDeLaMatriz($this->getJson('/api/catalogo-formativo?q=gestion&tipo=Especifica')))->toBe([]);
});

it('el filtro cobertura distingue con cursos, sin cursos y sin oferta', function () {
    $conCurso = CompetenciaModel::factory()->create(['nombre' => 'Con curso']);
    $soloMicro = CompetenciaModel::factory()->create(['nombre' => 'Solo micro']);
    CompetenciaModel::factory()->create(['nombre' => 'Nada']);
    $soloArchivado = CompetenciaModel::factory()->create(['nombre' => 'Solo archivado']);
    configurarFormativa($conCurso->id, null, [CursoModel::factory()->create()->id]);
    configurarFormativa($soloMicro->id, null, [], [MicrocontenidoModel::factory()->create()->id]);
    configurarFormativa($soloArchivado->id, null, [CursoModel::factory()->archivado()->create()->id]);
    actuarComoAdmin();

    expect(nombresDeLaMatriz($this->getJson('/api/catalogo-formativo?cobertura=con')))->toBe(['Con curso'])
        ->and(nombresDeLaMatriz($this->getJson('/api/catalogo-formativo?cobertura=sin')))->toBe(['Nada', 'Solo archivado', 'Solo micro'])
        ->and(nombresDeLaMatriz($this->getJson('/api/catalogo-formativo?cobertura=sin_oferta')))->toBe(['Nada', 'Solo archivado']);
});

it('los contadores cuentan solo recursos disponibles y el administrador no recibe nivel_actual', function () {
    $competencia = CompetenciaModel::factory()->create();
    configurarFormativa($competencia->id, null, [CursoModel::factory()->create()->id, CursoModel::factory()->archivado()->create()->id]);
    conNiveles([$competencia->id => 'A']);
    actuarComoAdmin();

    $fila = $this->getJson('/api/catalogo-formativo')->json('data.0');

    expect($fila['cursos_count'])->toBe(1)->and($fila)->not->toHaveKey('nivel_actual');
});

it('sin coincidencias devuelve una lista vacía y valida los parámetros', function () {
    CompetenciaModel::factory()->create();
    actuarComoAdmin();

    $this->getJson('/api/catalogo-formativo?q=inexistente')->assertOk()->assertJsonPath('data', []);
    $this->getJson('/api/catalogo-formativo?estado=Otro')->assertUnprocessable()->assertJsonValidationErrors('estado');
    $this->getJson('/api/catalogo-formativo?cobertura=xx')->assertUnprocessable()->assertJsonValidationErrors('cobertura');
    $this->getJson('/api/catalogo-formativo?tipo=Gerencial')->assertUnprocessable()->assertJsonValidationErrors('tipo');
    $this->getJson('/api/catalogo-formativo?per_page=51')->assertUnprocessable()->assertJsonValidationErrors('per_page');
});
