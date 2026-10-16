<?php

use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PlanAccionModel;

beforeEach(function () {
    $this->falsos = planFalsos(['hoy' => '2026-06-15']);
    $this->admin = actuarComoAdminDeTenant();
    $this->falsos['aprobadores']->administradores[$this->admin->id] = $this->admin->name;

    $this->uno = planGuardado(['nombre' => 'Plan uno', 'anio' => 2026, 'acciones' => [
        cuerpoAccion(['nombre' => 'Enero', 'tipo' => 'Virtual', 'fecha_inicio' => '2026-01-05', 'fecha_fin' => '2026-01-09', 'competencia_ids' => [1], 'colaborador_ids' => [10, 11]]),
        cuerpoAccion(['nombre' => 'Largo', 'tipo' => 'Presencial', 'fecha_inicio' => '2026-05-25', 'fecha_fin' => '2026-06-20', 'competencia_ids' => [2], 'colaborador_ids' => [10]]),
        ['nombre' => 'Sin fechas', 'competencia_ids' => [1]],
    ]]);
    $this->dos = planGuardado(['nombre' => 'Plan dos', 'anio' => 2027, 'acciones' => [cuerpoAccion(['nombre' => 'Del próximo año', 'fecha_inicio' => '2027-02-01', 'fecha_fin' => '2027-02-02'])]]);
    forzarEstadoPlan($this->dos['id'], 'Aprobado');
    PlanAccionModel::query()->whereKey($this->uno['acciones'][0]['id'])->update(['cancelada' => true, 'motivo_cancelacion' => 'Sin cupo']);
});

it('muestra por defecto las acciones del año actual con su estado calculado, inscritos y plan', function () {
    $respuesta = $this->getJson('/api/plan-acciones')->assertOk();

    expect(collect($respuesta->json('data'))->pluck('nombre')->all())->toBe(['Enero', 'Largo', 'Sin fechas'])
        ->and($respuesta->json('meta.total'))->toBe(3);
    $respuesta->assertJsonPath('data.0.estado_accion', 'Cancelada')
        ->assertJsonPath('data.0.cancelada', true)
        ->assertJsonPath('data.0.motivo_cancelacion', 'Sin cupo')
        ->assertJsonPath('data.0.inscritos', 2)
        ->assertJsonPath('data.0.plan.codigo', 'PLAN-0001')
        ->assertJsonPath('data.1.estado_accion', 'EnCurso')
        ->assertJsonPath('data.1.competencias.0.nombre', 'Competencia 2')
        ->assertJsonPath('data.2.estado_accion', 'Programada')
        ->assertJsonPath('data.2.fecha_inicio', null);
});

it('filtra por estado del plan, tipo y competencia', function () {
    $this->getJson('/api/plan-acciones?estado=Aprobado&anio=2027')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.nombre', 'Del próximo año');
    $this->getJson('/api/plan-acciones?estado=Borrador')->assertJsonPath('meta.total', 3);
    $this->getJson('/api/plan-acciones?tipo=Presencial')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.nombre', 'Largo');
    $this->getJson('/api/plan-acciones?competencia_id=1')->assertJsonPath('meta.total', 2);
});

it('devuelve las acciones que se solapan con el rango, también las de varios días', function () {
    $nombres = fn (string $consulta) => collect($this->getJson("/api/plan-acciones?{$consulta}")->assertOk()->json('data'))->pluck('nombre')->all();

    expect($nombres('fecha_desde=2026-06-01&fecha_hasta=2026-06-30'))->toBe(['Largo'])
        ->and($nombres('fecha_desde=2026-05-30&fecha_hasta=2026-05-31'))->toBe(['Largo'])
        ->and($nombres('fecha_desde=2026-07-01&fecha_hasta=2026-07-31'))->toBe([])
        ->and($nombres('fecha_desde=2026-01-09&fecha_hasta=2026-01-09'))->toBe(['Enero'])
        ->and($nombres('fecha_desde=2026-02-01'))->toBe(['Largo', 'Del próximo año']);
});

it('filtra por plan y pagina hasta 200 por página', function () {
    $this->getJson('/api/plan-acciones?plan_id='.$this->dos['id'])->assertJsonPath('meta.total', 1);
    $this->getJson('/api/plan-acciones?per_page=2&page=2')->assertJsonCount(1, 'data')->assertJsonPath('meta.last_page', 2);
    $this->getJson('/api/plan-acciones?per_page=200')->assertOk();
    $this->getJson('/api/plan-acciones?per_page=201')->assertUnprocessable();
    $this->getJson('/api/plan-acciones?estado=Otro')->assertUnprocessable();
    $this->getJson('/api/plan-acciones?fecha_desde=2026-06-10&fecha_hasta=2026-06-01')->assertUnprocessable();
});

it('no muestra acciones de otro tenant y exige rol de administrador', function () {
    actuarComoAdminDeTenant(2);
    $this->getJson('/api/plan-acciones')->assertOk()->assertJsonPath('meta.total', 0);

    actuarComoUsuarioDeTenant();
    $this->getJson('/api/plan-acciones')->assertForbidden();
    $this->app['auth']->forgetGuards();
    $this->getJson('/api/plan-acciones')->assertUnauthorized();
});
