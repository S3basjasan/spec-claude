<?php

use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PlanAccionModel;

beforeEach(function () {
    $this->falsos = actividadFalsos();
    $this->admin = actuarComoAdminDeTenant();
});

it('lista solo acciones no canceladas de planes Ejecutando sin actividad', function () {
    [, $libre] = accionEnPlan(['nombre' => 'Acción libre']);
    [, $conActividad] = accionEnPlan(['nombre' => 'Acción con actividad']);
    [, $cancelada] = accionEnPlan(['nombre' => 'Acción cancelada']);
    accionEnPlan(['nombre' => 'Acción de plan aprobado'], 'Aprobado');
    accionEnPlan(['nombre' => 'Acción de plan cerrado'], 'Cerrado');
    PlanAccionModel::query()->whereKey($cancelada)->update(['cancelada' => true]);
    actividadDe($conActividad);

    $r = $this->getJson('/api/actividades/acciones-disponibles')->assertOk();

    expect(collect($r->json('data'))->pluck('accion_id')->all())->toBe([$libre])
        ->and($r->json('data.0.nombre'))->toBe('Acción libre')
        ->and($r->json('data.0.competencias.0.nombre'))->toBe('Competencia 1')
        ->and($r->json('data.0.capacidad'))->toBe(20)
        ->and($r->json('data.0.inscritos'))->toBe(2)
        ->and($r->json('meta.total'))->toBe(1);
});

it('una acción deja de aparecer al crearle la actividad', function () {
    [, $accion] = accionEnPlan();
    $this->getJson('/api/actividades/acciones-disponibles')->assertJsonPath('meta.total', 1);

    actividadDe($accion);

    $this->getJson('/api/actividades/acciones-disponibles')->assertJsonPath('meta.total', 0)->assertJsonPath('data', []);
});

it('filtra por plan y por nombre sin distinguir mayúsculas y pagina', function () {
    [$planA] = accionEnPlan(['nombre' => 'Liderazgo situacional']);
    [$planB] = accionEnPlan(['nombre' => 'Excel avanzado']);
    accionEnPlan(['nombre' => 'Excel básico']);

    $this->getJson("/api/actividades/acciones-disponibles?plan_id={$planA}")->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.nombre', 'Liderazgo situacional');
    $this->getJson("/api/actividades/acciones-disponibles?plan_id={$planB}")->assertJsonPath('meta.total', 1);
    $this->getJson('/api/actividades/acciones-disponibles?q=EXCEL')->assertJsonPath('meta.total', 2);
    $this->getJson('/api/actividades/acciones-disponibles?q=nada')->assertJsonPath('meta.total', 0);
    $this->getJson('/api/actividades/acciones-disponibles?per_page=2&page=2')->assertJsonCount(1, 'data')->assertJsonPath('meta.last_page', 2);
    $this->getJson('/api/actividades/acciones-disponibles?per_page=51')->assertUnprocessable();
});

it('no ofrece acciones de otro tenant', function () {
    accionEnPlan();
    actuarComoAdminDeTenant(2);

    $this->getJson('/api/actividades/acciones-disponibles')->assertOk()->assertJsonPath('meta.total', 0);
});

it('lista los departamentos activos', function () {
    $this->getJson('/api/actividades/departamentos')->assertOk()
        ->assertExactJson(['data' => [['id' => 1, 'nombre' => 'Tecnología'], ['id' => 2, 'nombre' => 'Talento Humano']]]);
});

it('exige administrador en acciones disponibles y departamentos', function (string $ruta) {
    actuarComoUsuarioDeTenant();

    $this->getJson($ruta)->assertForbidden();
    app('auth')->forgetGuards();
    $this->getJson($ruta)->assertUnauthorized();
})->with(['/api/actividades/acciones-disponibles', '/api/actividades/departamentos']);
