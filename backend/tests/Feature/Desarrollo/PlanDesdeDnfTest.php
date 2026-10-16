<?php

use App\Modules\Desarrollo\Domain\Exceptions\DnfNoEncontrada;
use App\Modules\Desarrollo\Domain\Exceptions\DnfSinBrechas;
use App\Modules\Desarrollo\Infrastructure\Adapters\PlanDeFormacionDesdePlanes;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PlanModel;
use App\Modules\Desarrollo\Presentation\Facades\DesarrolloFacade;

beforeEach(function () {
    // Se usa el adaptador REAL del plan de formación; el resto de puertos de la DNF son dobles.
    $this->falsos = planFalsos();
    dnfFalsos(['plan' => app(PlanDeFormacionDesdePlanes::class)]);
    $this->admin = actuarComoAdminDeTenant();
    $this->dnf = dnfDeEjemplo([
        10 => [[1, 'A', 'C'], [2, 'B', 'C'], [4, 'B', 'B']],
        11 => [[1, 'A', 'D'], [3, 'A', 'D'], [5, 'B', null]],
    ], 'Departamento', 5);
});

it('crea el borrador con una acción por competencia con brecha y los colaboradores con brecha como participantes', function () {
    $planId = $this->postJson('/api/dnf/'.$this->dnf->id.'/plan')->assertCreated()->json('data.plan_formacion_id');

    $respuesta = $this->getJson("/api/planes/{$planId}")->assertOk();
    $respuesta->assertJsonPath('data.estado', 'Borrador')
        ->assertJsonPath('data.nombre', 'Plan DNF DNF-0001')
        ->assertJsonPath('data.anio', 2026)
        ->assertJsonPath('data.origen_dnf.id', $this->dnf->id)
        ->assertJsonPath('data.origen_dnf.codigo', 'DNF-0001')
        ->assertJsonPath('data.aprobador', null)
        ->assertJsonCount(3, 'data.acciones');
    $acciones = collect($respuesta->json('data.acciones'));
    expect($acciones->pluck('nombre')->all())->toBe(['Competencia 1', 'Competencia 2', 'Competencia 3'])
        ->and($acciones->map(fn ($a) => array_column($a['participantes'], 'colaborador_id'))->all())->toBe([[10, 11], [10], [11]])
        ->and($acciones->map(fn ($a) => array_column($a['competencias'], 'id'))->all())->toBe([[1], [2], [3]])
        ->and($acciones->pluck('tipo')->unique()->all())->toBe([null])
        ->and($acciones->pluck('completa')->unique()->all())->toBe([false]);
    expect((int) PlanModel::query()->find($planId)->created_by)->toBe($this->admin->id);
});

it('el borrador incompleto no se puede enviar a revisión: aplica las mismas reglas', function () {
    $planId = $this->postJson('/api/dnf/'.$this->dnf->id.'/plan')->json('data.plan_formacion_id');

    $this->patchJson("/api/planes/{$planId}/estado", ['estado' => 'Revision'])
        ->assertUnprocessable()->assertJsonPath('code', 'plan_incompleto');
});

it('una DNF solo puede generar un plan', function () {
    $this->postJson('/api/dnf/'.$this->dnf->id.'/plan')->assertCreated();

    $this->postJson('/api/dnf/'.$this->dnf->id.'/plan')->assertUnprocessable()->assertJsonPath('message', 'La DNF ya generó un plan.');
    expect(PlanModel::query()->count())->toBe(1);
});

it('crea el borrador aunque una competencia ya esté inactiva y evita nombres repetidos', function () {
    planGuardado(['nombre' => 'Plan DNF DNF-0001', 'anio' => 2026]);
    $planId = app(PlanDeFormacionDesdePlanes::class)->crearBorrador(1, $this->admin->id, $this->dnf->id, [
        ['colaborador_id' => 10, 'competencia_id' => 9],
        ['colaborador_id' => 10, 'competencia_id' => 9],
    ]);

    $plan = PlanModel::query()->findOrFail($planId);
    expect($plan->nombre)->toBe('Plan DNF DNF-0001 (2)')->and($plan->estado)->toBe('Borrador')->and((int) $plan->origen_dnf_id)->toBe($this->dnf->id);
    $this->getJson("/api/planes/{$planId}")->assertJsonCount(1, 'data.acciones')->assertJsonCount(1, 'data.acciones.0.participantes');
});

it('la Facade crea el borrador y rechaza brechas vacías o una DNF inexistente', function () {
    $planId = DesarrolloFacade::crearBorradorDesdeDnf(1, $this->admin->id, $this->dnf->id, [['colaborador_id' => 10, 'competencia_id' => 1]]);
    expect(PlanModel::query()->find($planId)->nombre)->toBe('Plan DNF DNF-0001');

    expect(fn () => DesarrolloFacade::crearBorradorDesdeDnf(1, $this->admin->id, $this->dnf->id, []))->toThrow(DnfSinBrechas::class);
    expect(fn () => DesarrolloFacade::crearBorradorDesdeDnf(1, $this->admin->id, 9999, [['colaborador_id' => 10, 'competencia_id' => 1]]))->toThrow(DnfNoEncontrada::class);
    expect(fn () => DesarrolloFacade::crearBorradorDesdeDnf(2, $this->admin->id, $this->dnf->id, [['colaborador_id' => 10, 'competencia_id' => 1]]))->toThrow(DnfNoEncontrada::class);
});
