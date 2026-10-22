<?php

use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\MicrocontenidoModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;

function titulosDeLaLista($respuesta): array
{
    return array_column($respuesta->json('data'), 'titulo');
}

it('por defecto lista solo Borrador y Publicado, más recientes primero', function () {
    microDe(['titulo' => 'Borrador uno']);
    microDe(['titulo' => 'Publicado uno', 'estado' => 'Publicado']);
    microDe(['titulo' => 'Archivado uno', 'estado' => 'Archivado']);
    actuarComoAdmin();

    expect(titulosDeLaLista($this->getJson('/api/microcontenidos')->assertOk()))->toBe(['Publicado uno', 'Borrador uno']);
});

it('filtra por estado, tipo, competencia y título sin tildes ni mayúsculas', function () {
    $competencia = CompetenciaModel::factory()->create();
    microDe(['titulo' => 'Gestión del tiempo', 'tipo' => 'Video', 'estado' => 'Publicado'], [$competencia->id]);
    microDe(['titulo' => 'Otro tema', 'tipo' => 'PDF', 'estado' => 'Archivado']);
    quizDe(1, ['titulo' => 'Quiz de gestion', 'estado' => 'Borrador']);
    actuarComoAdmin();

    expect(titulosDeLaLista($this->getJson('/api/microcontenidos?estado=Todos')))->toHaveCount(3)
        ->and(titulosDeLaLista($this->getJson('/api/microcontenidos?estado=Archivado')))->toBe(['Otro tema'])
        ->and(titulosDeLaLista($this->getJson('/api/microcontenidos?estado=Todos&tipo=Quiz')))->toBe(['Quiz de gestion'])
        ->and(titulosDeLaLista($this->getJson("/api/microcontenidos?competencia_id={$competencia->id}")))->toBe(['Gestión del tiempo'])
        ->and(titulosDeLaLista($this->getJson('/api/microcontenidos?q=GESTION&estado=Todos')))->toBe(['Quiz de gestion', 'Gestión del tiempo']);
});

it('muestra las competencias, el número de completaciones y si puede eliminarse', function () {
    $micro = microDe(['estado' => 'Publicado'], [
        CompetenciaModel::factory()->create(['nombre' => 'Liderazgo'])->id,
        CompetenciaModel::factory()->create(['nombre' => 'Negociación'])->id,
    ]);
    progresoMicro($micro->id, 1, 'Completado');
    progresoMicro($micro->id, 2, 'Completado');
    progresoMicro($micro->id, 3, 'EnProgreso');
    microDe(['estado' => 'Publicado']);
    actuarComoAdmin();

    $filas = $this->getJson('/api/microcontenidos')->assertOk()->json('data');
    $conProgreso = collect($filas)->firstWhere('id', $micro->id);
    $sinProgreso = collect($filas)->firstWhere('id', '!=', $micro->id);

    expect($conProgreso['completaciones'])->toBe(2)
        ->and($conProgreso['puede_eliminarse'])->toBeFalse()
        ->and($conProgreso['competencias'])->toHaveCount(2)
        ->and($sinProgreso['completaciones'])->toBe(0)
        ->and($sinProgreso['puede_eliminarse'])->toBeTrue();
});

it('pagina con page y per_page (máximo 50)', function () {
    for ($i = 0; $i < 5; $i++) {
        MicrocontenidoModel::factory()->create();
    }
    actuarComoAdmin();

    $respuesta = $this->getJson('/api/microcontenidos?per_page=2&page=2')->assertOk();
    expect($respuesta->json('data'))->toHaveCount(2)->and($respuesta->json('meta.total'))->toBe(5)->and($respuesta->json('meta.last_page'))->toBe(3);

    $this->getJson('/api/microcontenidos?per_page=51')->assertUnprocessable()->assertJsonValidationErrors('per_page');
    $this->getJson('/api/microcontenidos?estado=Raro')->assertUnprocessable()->assertJsonValidationErrors('estado');
});

it('devuelve las competencias para el formulario y el filtro', function () {
    $activa = CompetenciaModel::factory()->create(['nombre' => 'Activa']);
    $inactiva = CompetenciaModel::factory()->inactiva()->create(['nombre' => 'Inactiva']);
    actuarComoAdmin();

    $datos = $this->getJson('/api/microcontenidos/opciones')->assertOk()->json('data');

    expect(array_column($datos['competencias_activas'], 'id'))->toContain($activa->id)->not->toContain($inactiva->id)
        ->and(array_column($datos['competencias'], 'id'))->toContain($activa->id)->toContain($inactiva->id);
});

it('exige sesión y rechaza al colaborador en opciones', function () {
    $this->getJson('/api/microcontenidos')->assertUnauthorized();

    actuarComoUsuario();
    $this->getJson('/api/microcontenidos/opciones')->assertForbidden();
});
