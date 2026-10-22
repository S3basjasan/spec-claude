<?php

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CategoriaModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaGradoModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;
use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Laravel\Sanctum\Sanctum;

/**
 * Cuerpo del PUT de la competencia y sus grados.
 *
 * @param  array<string, mixed>  $cambios
 * @return array<string, mixed>
 */
function cuerpoDeEdicionCompetencia(CompetenciaModel $competencia, array $cambios = []): array
{
    return array_replace([
        'version' => $competencia->version,
        'nombre' => $competencia->nombre,
        'descripcion' => $competencia->descripcion,
        'tipo' => $competencia->tipo,
        'tipo_grado_id' => escalaId('Letras'),
        'grados' => ['Nuevo A', 'Nuevo B', 'Nuevo C', 'Nuevo D'],
    ], $cambios);
}

it('actualiza nombre, descripción, tipo y grados conservando código, estado y autor', function () {
    $creador = UserModel::factory()->admin()->create();
    $competencia = CompetenciaModel::factory()->create(['nombre' => 'Original', 'created_by' => $creador->id]);
    $codigo = $competencia->fresh()->codigo;
    $editor = UserModel::factory()->admin()->create();
    Sanctum::actingAs($editor, ['admin']);

    $this->putJson("/api/competencias/{$competencia->id}", cuerpoDeEdicionCompetencia($competencia, [
        'nombre' => 'Renombrada',
        'descripcion' => 'Nueva descripción',
        'tipo' => 'Especifica',
    ]))
        ->assertOk()
        ->assertJsonPath('data.nombre', 'Renombrada')
        ->assertJsonPath('data.tipo', 'Especifica')
        ->assertJsonPath('data.codigo', $codigo)
        ->assertJsonPath('data.estado', 'Activo')
        ->assertJsonPath('data.version', 2)
        ->assertJsonPath('data.grados.*.descripcion', ['Nuevo A', 'Nuevo B', 'Nuevo C', 'Nuevo D']);

    $guardada = $competencia->fresh();
    expect($guardada->nombre_clave)->toBe('renombrada')
        ->and($guardada->created_by)->toBe($creador->id)
        ->and($guardada->updated_by)->toBe($editor->id);
});

it('actualiza las mismas filas de grado, sin borrarlas ni recrearlas', function () {
    $competencia = CompetenciaModel::factory()->create();
    $antes = CompetenciaGradoModel::query()->where('competencia_id', $competencia->id)->orderBy('id')->pluck('id')->all();
    actuarComoAdmin();

    $this->putJson("/api/competencias/{$competencia->id}", cuerpoDeEdicionCompetencia($competencia))->assertOk();

    expect(CompetenciaGradoModel::query()->where('competencia_id', $competencia->id)->orderBy('id')->pluck('id')->all())->toBe($antes);
});

it('conservar el propio nombre no es un duplicado, pero el de otra competencia sí', function () {
    $una = CompetenciaModel::factory()->create(['nombre' => 'Liderazgo']);
    CompetenciaModel::factory()->create(['nombre' => 'Empatía']);
    actuarComoAdmin();

    $this->putJson("/api/competencias/{$una->id}", cuerpoDeEdicionCompetencia($una, ['nombre' => ' liderazgo ']))->assertOk();

    $this->putJson("/api/competencias/{$una->id}", cuerpoDeEdicionCompetencia($una->fresh(), ['nombre' => 'EMPATIA']))
        ->assertUnprocessable()
        ->assertJsonPath('errors.nombre.0', 'Ya existe una competencia con este nombre.');
});

it('rechaza código y estado en el cuerpo con 422', function () {
    $competencia = CompetenciaModel::factory()->create();
    actuarComoAdmin();

    $this->putJson("/api/competencias/{$competencia->id}", cuerpoDeEdicionCompetencia($competencia, ['codigo' => 'COMP-9999']))
        ->assertUnprocessable()->assertJsonValidationErrors('codigo');
    $this->putJson("/api/competencias/{$competencia->id}", cuerpoDeEdicionCompetencia($competencia, ['estado' => 'Inactivo']))
        ->assertUnprocessable()->assertJsonValidationErrors('estado');

    expect($competencia->fresh()->estado)->toBe('Activo');
});

it('permite editar una competencia inactiva y la mantiene inactiva', function () {
    $inactiva = CompetenciaModel::factory()->inactiva()->create();
    actuarComoAdmin();

    $this->putJson("/api/competencias/{$inactiva->id}", cuerpoDeEdicionCompetencia($inactiva, ['nombre' => 'Retirada y editada']))
        ->assertOk()
        ->assertJsonPath('data.estado', 'Inactivo');
});

it('reemplaza las categorías cuando llegan y conserva las guardadas cuando se omiten', function () {
    $competencia = CompetenciaModel::factory()->conCategorias()->create();
    actuarComoAdmin();
    $categorias = fn () => CategoriaModel::query()->where('competencia_id', $competencia->id)->orderBy('orden')->pluck('nombre')->all();

    $this->putJson("/api/competencias/{$competencia->id}", cuerpoDeEdicionCompetencia($competencia, [
        'tipo_grado_id' => escalaId('Categorías'),
        'categorias' => ['Inicial', 'Competente', 'Avanzado', 'Maestría'],
    ]))->assertOk();
    expect($categorias())->toBe(['Inicial', 'Competente', 'Avanzado', 'Maestría']);

    $this->putJson("/api/competencias/{$competencia->id}", cuerpoDeEdicionCompetencia($competencia->fresh(), ['tipo_grado_id' => escalaId('Letras')]))
        ->assertOk()
        ->assertJsonPath('data.grados.0.etiqueta', 'Grado A')
        ->assertJsonPath('data.categorias', ['Inicial', 'Competente', 'Avanzado', 'Maestría']);
    expect($categorias())->toBe(['Inicial', 'Competente', 'Avanzado', 'Maestría']);
});

it('exige los cuatro nombres al pasar a la escala Categorías sin nombres propios', function () {
    $competencia = CompetenciaModel::factory()->create();
    actuarComoAdmin();

    $this->putJson("/api/competencias/{$competencia->id}", cuerpoDeEdicionCompetencia($competencia, ['tipo_grado_id' => escalaId('Categorías')]))
        ->assertUnprocessable()->assertJsonValidationErrors('categorias');
});

it('rechaza con 403 al Usuario, 401 sin sesión y 404 si no existe', function () {
    $competencia = CompetenciaModel::factory()->create();

    $this->putJson("/api/competencias/{$competencia->id}", cuerpoDeEdicionCompetencia($competencia))->assertUnauthorized();

    actuarComoUsuario();
    $this->putJson("/api/competencias/{$competencia->id}", cuerpoDeEdicionCompetencia($competencia))->assertForbidden();

    actuarComoAdmin();
    $this->putJson('/api/competencias/99999', cuerpoDeEdicionCompetencia($competencia))->assertNotFound();
});

it('exige la versión', function () {
    $competencia = CompetenciaModel::factory()->create();
    actuarComoAdmin();
    $cuerpo = cuerpoDeEdicionCompetencia($competencia);
    unset($cuerpo['version']);

    $this->putJson("/api/competencias/{$competencia->id}", $cuerpo)->assertUnprocessable()->assertJsonValidationErrors('version');
});
