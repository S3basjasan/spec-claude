<?php

use App\Modules\Estructura\Domain\Exceptions\ConflictoDeVersion;
use App\Modules\Estructura\Domain\Repositories\CompetenciaRepositoryInterface;
use App\Modules\Estructura\Domain\ValueObjects\EstadoCompetencia;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\PreguntaModel;

const MENSAJE_DE_CONFLICTO = 'Esta competencia cambió mientras la editabas. Recarga para ver la versión actual.';

it('rechaza con 409 un PUT con versión desfasada y no escribe nada', function () {
    $competencia = CompetenciaModel::factory()->create(['nombre' => 'Original']);
    actuarComoAdmin();

    $this->putJson("/api/competencias/{$competencia->id}", cuerpoDeEdicionCompetencia($competencia, ['nombre' => 'Primero']))->assertOk();

    $this->putJson("/api/competencias/{$competencia->id}", cuerpoDeEdicionCompetencia($competencia, ['nombre' => 'Segundo', 'version' => 1]))
        ->assertStatus(409)
        ->assertExactJson(['message' => MENSAJE_DE_CONFLICTO]);

    expect($competencia->fresh()->nombre)->toBe('Primero')
        ->and($competencia->fresh()->version)->toBe(2);
});

it('rechaza con 409 los PUT de comportamientos y de preguntas con versión desfasada', function () {
    $competencia = CompetenciaModel::factory()->create();
    actuarComoAdmin();
    $this->putJson("/api/competencias/{$competencia->id}/preguntas", ['version' => 1, 'preguntas' => ['a', 'b', 'c', 'd']])->assertOk();

    $this->putJson("/api/competencias/{$competencia->id}/preguntas", ['version' => 1, 'preguntas' => ['x', 'y', 'z', 'w']])
        ->assertStatus(409)->assertExactJson(['message' => MENSAJE_DE_CONFLICTO]);
    $this->putJson("/api/competencias/{$competencia->id}/comportamientos", ['version' => 1, 'comportamientos' => comportamientosNuevos()])
        ->assertStatus(409);

    expect(PreguntaModel::query()->orderBy('orden')->pluck('pregunta')->all())->toBe(['a', 'b', 'c', 'd']);
});

it('un cambio de estado o de escala deja desfasado el formulario que se abrió antes', function () {
    $competencia = CompetenciaModel::factory()->create();
    actuarComoAdmin();

    $this->patchJson("/api/competencias/{$competencia->id}/estado", ['estado' => 'Inactivo'])->assertOk();
    $this->putJson("/api/competencias/{$competencia->id}", cuerpoDeEdicionCompetencia($competencia))->assertStatus(409);

    $otra = CompetenciaModel::factory()->create();
    $this->patchJson("/api/competencias/{$otra->id}/escala", ['tipo_grado_id' => escalaId('Números')])->assertOk();
    $this->putJson("/api/competencias/{$otra->id}/preguntas", ['version' => 1, 'preguntas' => ['a', 'b', 'c', 'd']])->assertStatus(409);
});

it('resuelve en una sola sentencia atómica: con la misma versión una gana y la otra recibe el conflicto', function () {
    $competencia = CompetenciaModel::factory()->create();
    $repositorio = app(CompetenciaRepositoryInterface::class);

    $repositorio->actualizarPreguntas($competencia->id, 1, ['a', 'b', 'c', 'd'], null);

    expect(fn () => $repositorio->actualizarPreguntas($competencia->id, 1, ['x', 'y', 'z', 'w'], null))
        ->toThrow(ConflictoDeVersion::class);
    expect(PreguntaModel::query()->orderBy('orden')->pluck('pregunta')->all())->toBe(['a', 'b', 'c', 'd']);
});

it('las acciones de estado y escala se aplican sobre la versión vigente sin exigir versión', function () {
    $competencia = CompetenciaModel::factory()->create();
    $repositorio = app(CompetenciaRepositoryInterface::class);
    $repositorio->actualizarPreguntas($competencia->id, 1, ['a', 'b', 'c', 'd'], null);

    $resultado = $repositorio->cambiarEstado($competencia->id, EstadoCompetencia::Inactivo, null);

    expect($resultado->version)->toBe(3);
});
