<?php

use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PlanAccionModel;
use Laravel\Sanctum\Sanctum;

/**
 * Crea un plan con el colaborador como participante y lo deja en el estado indicado.
 *
 * @param  list<array<string, mixed>>  $acciones
 */
function planConColaborador(string $estado, array $acciones, int $actor, int $colaborador, string $nombre = 'Plan'): array
{
    $plan = planGuardado([
        'nombre' => $nombre,
        'aprobador_id' => $actor,
        'acciones' => array_map(fn (array $a) => cuerpoAccion($a + ['colaborador_ids' => [$colaborador]]), $acciones),
    ], actorId: $actor);
    forzarEstadoPlan($plan['id'], $estado);

    return $plan;
}

beforeEach(function () {
    // El administrador crea los planes; después se actúa como el colaborador.
    $this->admin = actuarComoAdminDeTenant();
    [$this->usuario, $this->colaborador] = colaboradorConCuentaDePlan();
    $this->falsos = planFalsos(['hoy' => '2026-10-16', 'vigentes' => [$this->colaborador => 'Diego Paredes', 99 => 'Otra persona'], 'administradores' => [$this->admin->id => $this->admin->name]]);
    $this->aprobado = planConColaborador('Aprobado', [
        ['nombre' => 'A1 finalizada', 'fecha_inicio' => '2026-02-02', 'fecha_fin' => '2026-02-27'],
        ['nombre' => 'A2 en curso', 'fecha_inicio' => '2026-10-05', 'fecha_fin' => '2026-10-23'],
        ['nombre' => 'A3 próxima', 'fecha_inicio' => '2026-10-27', 'fecha_fin' => '2026-11-10'],
        ['nombre' => 'A4 próxima', 'fecha_inicio' => '2026-11-03', 'fecha_fin' => '2026-11-04'],
        ['nombre' => 'A5 lejana', 'fecha_inicio' => '2026-12-01', 'fecha_fin' => '2026-12-02'],
        ['nombre' => 'A6 cancelada', 'fecha_inicio' => '2026-10-20', 'fecha_fin' => '2026-10-21'],
    ], $this->admin->id, $this->colaborador, 'Plan aprobado');
    PlanAccionModel::query()->whereKey($this->aprobado['acciones'][5]['id'])->update(['cancelada' => true, 'motivo_cancelacion' => 'Sin cupo']);
    Sanctum::actingAs($this->usuario, ['user']);
    $this->miPlan = fn () => $this->getJson('/api/planes/mi-plan')->assertOk();
});

it('muestra solo sus acciones de planes Aprobado, Ejecutando y Cerrado en orden cronológico', function () {
    Sanctum::actingAs($this->admin, ['admin']);
    planConColaborador('Borrador', [['nombre' => 'Oculta borrador']], $this->admin->id, $this->colaborador, 'Plan borrador');
    planConColaborador('Revision', [['nombre' => 'Oculta revisión']], $this->admin->id, $this->colaborador, 'Plan revisión');
    planConColaborador('Ejecutando', [['nombre' => 'B ejecutando', 'fecha_inicio' => '2026-04-01', 'fecha_fin' => '2026-04-02']], $this->admin->id, $this->colaborador, 'Plan ejecutando');
    planConColaborador('Cerrado', [['nombre' => 'C cerrado', 'fecha_inicio' => '2026-03-01', 'fecha_fin' => '2026-03-02']], $this->admin->id, $this->colaborador, 'Plan cerrado');
    planConColaborador('Aprobado', [['nombre' => 'Otro colaborador']], $this->admin->id, 99, 'Plan de otra persona');
    Sanctum::actingAs($this->usuario, ['user']);

    $nombres = collect(($this->miPlan)()->json('data.acciones'))->pluck('nombre')->all();

    expect($nombres)->toBe(['A1 finalizada', 'C cerrado', 'B ejecutando', 'A2 en curso', 'A6 cancelada', 'A3 próxima', 'A4 próxima', 'A5 lejana']);
});

it('cada acción trae nombre, competencia, fechas, formador, estado y plan', function () {
    $respuesta = ($this->miPlan)();

    $respuesta->assertJsonPath('data.acciones.0.nombre', 'A1 finalizada')
        ->assertJsonPath('data.acciones.0.competencias.0.nombre', 'Competencia 1')
        ->assertJsonPath('data.acciones.0.fecha_inicio', '2026-02-02')
        ->assertJsonPath('data.acciones.0.formador', 'Ing. Vera')
        ->assertJsonPath('data.acciones.0.estado_accion', 'Finalizada')
        ->assertJsonPath('data.acciones.0.plan.nombre', 'Plan aprobado')
        ->assertJsonPath('data.acciones.1.estado_accion', 'EnCurso');
});

it('calcula el porcentaje completado con acciones Finalizadas con asistencia Presente sobre las asignadas no canceladas', function () {
    planFalsos(['hoy' => '2026-10-16', 'vigentes' => [$this->colaborador => 'Diego'], 'presentes' => [$this->colaborador => [$this->aprobado['acciones'][0]['id']]]]);

    ($this->miPlan)()->assertJsonPath('data.porcentaje_completado', 20)
        ->assertJsonPath('data.acciones_asignadas', 5)
        ->assertJsonPath('data.acciones_completadas', 1)
        ->assertJsonPath('data.acciones.0.completada', true);
});

it('con 4 acciones asignadas y 1 completada el progreso es 25 %', function () {
    PlanAccionModel::query()->whereKey($this->aprobado['acciones'][4]['id'])->update(['cancelada' => true, 'motivo_cancelacion' => 'x']);
    planFalsos(['hoy' => '2026-10-16', 'vigentes' => [$this->colaborador => 'Diego'], 'presentes' => [$this->colaborador => [$this->aprobado['acciones'][0]['id']]]]);

    ($this->miPlan)()->assertJsonPath('data.porcentaje_completado', 25)->assertJsonPath('data.acciones_asignadas', 4);
});

it('una acción finalizada sin asistencia registrada no cuenta como completada (progreso 0 %)', function () {
    ($this->miPlan)()->assertJsonPath('data.porcentaje_completado', 0)->assertJsonPath('data.acciones_completadas', 0)->assertJsonPath('data.acciones.0.completada', false);
});

it('muestra las tres próximas actividades no canceladas con inicio de hoy en adelante, las más cercanas primero', function () {
    $proximas = collect(($this->miPlan)()->json('data.proximas'))->pluck('nombre')->all();

    expect($proximas)->toBe(['A3 próxima', 'A4 próxima', 'A5 lejana']);
});

it('las acciones canceladas se ven con su motivo y no cuentan en el progreso ni en las próximas', function () {
    $respuesta = ($this->miPlan)();

    $cancelada = collect($respuesta->json('data.acciones'))->firstWhere('nombre', 'A6 cancelada');
    expect($cancelada['estado_accion'])->toBe('Cancelada')->and($cancelada['motivo_cancelacion'])->toBe('Sin cupo')
        ->and($respuesta->json('data.acciones_asignadas'))->toBe(5)
        ->and(collect($respuesta->json('data.proximas'))->pluck('nombre')->all())->not->toContain('A6 cancelada');
});

it('devuelve una respuesta vacía si no tiene acciones o su usuario no tiene ficha', function () {
    [$otro] = colaboradorConCuentaDePlan();
    $this->getJson('/api/planes/mi-plan')->assertOk()->assertJsonPath('data', ['porcentaje_completado' => 0, 'acciones_asignadas' => 0, 'acciones_completadas' => 0, 'acciones' => [], 'proximas' => []]);

    actuarComoUsuarioDeTenant();
    $this->getJson('/api/planes/mi-plan')->assertOk()->assertJsonPath('data.acciones', []);
    expect($otro)->not->toBeNull();
});

it('no revela datos de otro colaborador ni de otro tenant, y exige autenticación', function () {
    [, $otraFicha] = colaboradorConCuentaDePlan(2);
    $this->getJson('/api/planes/mi-plan')->assertOk()->assertJsonPath('data.acciones', []);
    expect($otraFicha)->toBeInt();

    $this->app['auth']->forgetGuards();
    $this->getJson('/api/planes/mi-plan')->assertUnauthorized();
});
