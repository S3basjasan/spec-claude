<?php

use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\CursoModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\MicrocontenidoModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;

it('devuelve el detalle con grados, área y solo recursos disponibles, sin datos de gestión', function () {
    $competencia = CompetenciaModel::factory()->create();
    $disponible = CursoModel::factory()->conDestino('/cursos/9')->create(['nombre' => 'Curso visible']);
    $archivado = CursoModel::factory()->archivado()->create();
    $micro = MicrocontenidoModel::factory()->create();
    configurarFormativa($competencia->id, null, [$disponible->id, $archivado->id], [$micro->id]);
    conNiveles([$competencia->id => 'C']);
    conBrechas([['competencia_id' => $competencia->id, 'nivel_requerido' => 'A']]);
    actuarComoUsuario();

    $detalle = $this->getJson("/api/catalogo-formativo/{$competencia->id}")->assertOk()->json('data');

    expect(array_keys($detalle['competencia']['grados']))->toBe(['A', 'B', 'C', 'D'])
        ->and(array_column($detalle['cursos'], 'nombre'))->toBe(['Curso visible'])
        ->and($detalle['cursos'][0]['accion'])->toBe('Iniciar')
        ->and($detalle['cursos'][0]['url_destino'])->toBe('/cursos/9')
        ->and($detalle['microcontenidos'][0]['accion'])->toBe('Ver')
        ->and($detalle['nivel_actual'])->toBe('C')
        ->and($detalle['nivel_requerido'])->toBe('A')
        ->and($detalle)->not->toHaveKeys(['version', 'ultima_configuracion']);
});

it('sin brecha no devuelve nivel requerido', function () {
    $competencia = CompetenciaModel::factory()->create();
    conNiveles([]);
    conBrechas([]);
    actuarComoUsuario();

    $detalle = $this->getJson("/api/catalogo-formativo/{$competencia->id}")->json('data');

    expect($detalle['nivel_actual'])->toBeNull()->and($detalle['nivel_requerido'])->toBeNull();
});

it('una competencia inactiva es 404 para el colaborador y una inexistente también', function () {
    $inactiva = CompetenciaModel::factory()->inactiva()->create();
    actuarComoUsuario();

    $this->getJson("/api/catalogo-formativo/{$inactiva->id}")->assertNotFound()->assertJson(['message' => 'La competencia no existe.']);
    $this->getJson('/api/catalogo-formativo/999999')->assertNotFound();
});

it('si los puertos fallan, el detalle responde con niveles null', function () {
    $competencia = CompetenciaModel::factory()->create();
    conNiveles([], true);
    conBrechas([], true);
    actuarComoUsuario();

    $this->getJson("/api/catalogo-formativo/{$competencia->id}")->assertOk()->assertJsonPath('data.nivel_actual', null);
});

it('exige sesión para el detalle', function () {
    $this->getJson('/api/catalogo-formativo/1')->assertUnauthorized();
});
