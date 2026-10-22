<?php

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\PuestoCompetenciaModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\PuestoModel;
use App\Modules\Estructura\Presentation\Facades\EstructuraFacade;

/**
 * Puesto cuya única competencia asignada es la indicada, con el grado requerido dado.
 */
function puestoConCompetencia(CompetenciaModel $competencia, string $grado): PuestoModel
{
    $puesto = PuestoModel::factory()->create();
    PuestoCompetenciaModel::query()->where('puesto_id', $puesto->id)->delete();
    PuestoCompetenciaModel::query()->create(['puesto_id' => $puesto->id, 'competencia_id' => $competencia->id, 'grado_requerido' => $grado]);

    return $puesto;
}

it('conserva y permite consultar la asignación de una competencia que luego se desactiva', function () {
    $competencia = CompetenciaModel::factory()->create();
    $puesto = puestoConCompetencia($competencia, 'B');

    actuarComoAdmin();
    $this->patchJson("/api/competencias/{$competencia->id}/estado", ['estado' => 'Inactivo'])->assertOk();

    $dato = EstructuraFacade::getPuesto($puesto->id);
    expect($dato['competencias'])->toHaveCount(1)
        ->and($dato['competencias'][0]['competencia_id'])->toBe($competencia->id)
        ->and($dato['competencias'][0]['estado'])->toBe('Inactivo')
        ->and($dato['competencias'][0]['grado_requerido'])->toBe('B');
});

it('no ofrece las competencias inactivas para asignarlas a un puesto', function () {
    $activa = CompetenciaModel::factory()->create();
    $inactiva = CompetenciaModel::factory()->inactiva()->create();
    actuarComoAdmin();

    $ids = collect($this->getJson('/api/puestos/competencias-disponibles')->assertOk()->json('data'))->pluck('competencia_id')->all();

    expect($ids)->toContain($activa->id)->and($ids)->not->toContain($inactiva->id);
});

it('rechaza asignar una competencia inactiva o un grado que no pertenece a la competencia', function () {
    $activa = CompetenciaModel::factory()->create();
    $inactiva = CompetenciaModel::factory()->inactiva()->create();
    actuarComoAdmin();

    $this->postJson('/api/puestos', datosPuesto($activa->id, ['competencias' => [['competencia_id' => $inactiva->id, 'grado_requerido' => 'A']]]))
        ->assertUnprocessable()->assertJsonValidationErrors('competencias.0.competencia_id');

    $this->postJson('/api/puestos', datosPuesto($activa->id, ['competencias' => [['competencia_id' => $activa->id, 'grado_requerido' => 'E']]]))
        ->assertUnprocessable()->assertJsonValidationErrors('competencias.0.grado_requerido');
});

it('cambiar la escala de la competencia no altera el grado requerido de las asignaciones', function () {
    $competencia = CompetenciaModel::factory()->create();
    $puesto = puestoConCompetencia($competencia, 'C');

    actuarComoAdmin();
    $this->patchJson("/api/competencias/{$competencia->id}/escala", ['tipo_grado_id' => escalaId('Categorías')])->assertOk();

    expect(EstructuraFacade::getPuesto($puesto->id)['competencias'][0]['grado_requerido'])->toBe('C');
});
