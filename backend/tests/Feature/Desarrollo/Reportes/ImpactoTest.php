<?php

use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\ImpactoAccionModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PlanAccionModel;

beforeEach(function () {
    // Hoy = 2026-10-17: la acción por defecto (costo 800) ya está Finalizada.
    $this->falsos = actividadFalsos(['hoy' => '2026-10-17', 'vigentes' => [10 => 'Ana', 11 => 'Luis']]);
    departamentosActuales([10 => 1, 11 => 1]);
    $this->guardar = fn (int $accion, array $cambios = []) => $this->putJson("/api/reportes/impactos/$accion", cuerpoImpacto($cambios));
    $this->listar = fn (array $q = []) => $this->getJson('/api/reportes/impactos?'.http_build_query($q));
    $this->admin = actuarComoAdminDeTenant();
});

it('crea la evaluación con ROI y registra quién la creó', function () {
    [, $accion] = accionEnPlan(['colaborador_ids' => [10, 11]]);

    ($this->guardar)($accion)->assertOk()
        ->assertJsonPath('data.plan_accion_id', $accion)
        ->assertJsonPath('data.indicador', 'Tiempo de cierre contable')
        ->assertJsonPath('data.beneficio_estimado', 5000)
        ->assertJsonPath('data.roi', 525)
        ->assertJsonPath('data.creado_por', $this->admin->name)
        ->assertJsonPath('data.modificado_por', $this->admin->name);

    $m = ImpactoAccionModel::query()->firstOrFail();
    expect($m->created_by)->toBe($this->admin->id)->and($m->updated_by)->toBe($this->admin->id)->and($m->tenant_id)->toBe(1);
});

it('actualiza la misma evaluación (una sola por acción) y registra quién la modificó', function () {
    [, $accion] = accionEnPlan(['colaborador_ids' => [10]]);
    ($this->guardar)($accion)->assertOk();
    $otro = actuarComoAdminDeTenant();

    ($this->guardar)($accion, ['beneficio_estimado' => 1600, 'justificacion' => 'Ajuste'])->assertOk()
        ->assertJsonPath('data.roi', 100)->assertJsonPath('data.justificacion', 'Ajuste')
        ->assertJsonPath('data.creado_por', $this->admin->name)->assertJsonPath('data.modificado_por', $otro->name);

    expect(ImpactoAccionModel::query()->count())->toBe(1)
        ->and(ImpactoAccionModel::query()->first()->created_by)->toBe($this->admin->id)
        ->and(ImpactoAccionModel::query()->first()->updated_by)->toBe($otro->id);
});

it('el ROI no aplica con costo cero', function () {
    [, $accion] = accionEnPlan(['costo' => 0, 'colaborador_ids' => [10]]);

    ($this->guardar)($accion)->assertOk()->assertJsonPath('data.roi', null);
});

it('permite evaluar aunque el plan esté Cerrado', function () {
    [, $accion] = accionEnPlan(['colaborador_ids' => [10]], 'Cerrado');

    ($this->guardar)($accion)->assertOk();
});

it('valida los campos', function (string $campo, mixed $valor) {
    [, $accion] = accionEnPlan(['colaborador_ids' => [10]]);

    ($this->guardar)($accion, [$campo => $valor])->assertUnprocessable()->assertJsonValidationErrors($campo);
    expect(ImpactoAccionModel::query()->count())->toBe(0);
})->with([
    ['indicador', ''], ['indicador', null], ['indicador', str_repeat('a', 151)],
    ['unidad', ''], ['unidad', str_repeat('a', 51)],
    ['valor_antes', 'abc'], ['valor_antes', null], ['valor_despues', 'abc'], ['valor_despues', null],
    ['beneficio_estimado', -1], ['beneficio_estimado', 'mucho'], ['beneficio_estimado', null],
    ['justificacion', ''], ['justificacion', str_repeat('a', 2001)],
]);

it('rechaza una acción que no está Finalizada', function (array $fechas) {
    [, $accion] = accionEnPlan($fechas + ['colaborador_ids' => [10]]);

    ($this->guardar)($accion)->assertUnprocessable()->assertJsonPath('code', 'accion_no_finalizada');
})->with([
    'en curso' => [['fecha_inicio' => '2026-10-10', 'fecha_fin' => '2026-10-30']],
    'programada' => [['fecha_inicio' => '2026-12-01', 'fecha_fin' => '2026-12-10']],
]);

it('rechaza una acción cancelada o de un plan que no está en ejecución ni cerrado', function () {
    [, $cancelada] = accionEnPlan(['colaborador_ids' => [10]]);
    PlanAccionModel::query()->whereKey($cancelada)->update(['cancelada' => true]);
    [, $borrador] = accionEnPlan(['colaborador_ids' => [10]], 'Borrador');

    ($this->guardar)($cancelada)->assertUnprocessable()->assertJsonPath('code', 'accion_no_finalizada');
    ($this->guardar)($borrador)->assertUnprocessable()->assertJsonPath('code', 'accion_no_finalizada');
});

it('responde 404 con una acción inexistente', function () {
    ($this->guardar)(9999)->assertNotFound()->assertJsonPath('code', 'accion_no_encontrada');
});

it('lista las acciones Finalizadas con su evaluación y ROI, con filtros y paginación', function () {
    [$plan1, $a1] = accionEnPlan(['nombre' => 'Con impacto', 'colaborador_ids' => [10]]);
    [, $a2] = accionEnPlan(['nombre' => 'Sin impacto', 'fecha_fin' => '2026-03-10', 'colaborador_ids' => [10]]);
    accionEnPlan(['nombre' => 'En curso', 'fecha_inicio' => '2026-10-10', 'fecha_fin' => '2026-10-30', 'colaborador_ids' => [10]]);
    impactoDeAccion($a1, 1600);

    $todas = ($this->listar)()->assertOk()->assertJsonPath('meta.total', 2);
    $porNombre = collect($todas->json('data'))->keyBy('accion_nombre');

    expect($porNombre['Con impacto']['evaluada'])->toBeTrue()->and($porNombre['Con impacto']['roi'])->toEqual(100)
        ->and($porNombre['Con impacto']['impacto']['beneficio_estimado'])->toEqual(1600)
        ->and($porNombre['Sin impacto']['evaluada'])->toBeFalse()->and($porNombre['Sin impacto']['roi'])->toBeNull()->and($porNombre['Sin impacto']['impacto'])->toBeNull()
        ->and($porNombre['Con impacto']['costo'])->toEqual(800);

    expect(array_column(($this->listar)(['evaluado' => 'con'])->json('data'), 'plan_accion_id'))->toBe([$a1])
        ->and(array_column(($this->listar)(['evaluado' => 'sin'])->json('data'), 'plan_accion_id'))->toBe([$a2])
        ->and(array_column(($this->listar)(['plan_id' => $plan1])->json('data'), 'plan_accion_id'))->toBe([$a1]);
    ($this->listar)(['per_page' => 1, 'page' => 2])->assertOk()->assertJsonCount(1, 'data');
    ($this->listar)(['evaluado' => 'tal'])->assertUnprocessable()->assertJsonValidationErrors('evaluado');
});

it('el ROI estimado del dashboard cambia al registrar la evaluación', function () {
    [, $accion] = accionEnPlan(['colaborador_ids' => [10]]);
    $antes = $this->getJson('/api/reportes/dashboard')->json('data.indicadores.roi_estimado.valor');

    ($this->guardar)($accion, ['beneficio_estimado' => 2400])->assertOk();
    $r = $this->getJson('/api/reportes/dashboard')->assertOk();

    expect($antes)->toBeNull()->and($r->json('data.indicadores.roi_estimado.valor'))->toEqual(200)
        ->and($r->json('data.indicadores.roi_estimado.acciones_evaluadas'))->toBe(1);
});

it('exige rol de administrador', function () {
    [, $accion] = accionEnPlan(['colaborador_ids' => [10]]);
    actuarComoUsuarioDeTenant();

    ($this->guardar)($accion)->assertForbidden();
    ($this->listar)()->assertForbidden();
});

it('no entrega ni modifica evaluaciones de otro tenant', function () {
    [, $accion] = accionEnPlan(['colaborador_ids' => [10]]);
    impactoDeAccion($accion, 100, tenantId: 2);

    expect(($this->listar)()->json('data.0.evaluada'))->toBeFalse();
});
