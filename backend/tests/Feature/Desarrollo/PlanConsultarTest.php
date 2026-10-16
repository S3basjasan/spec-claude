<?php

use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\CursoModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PlanAccionModel;

beforeEach(function () {
    $this->falsos = planFalsos();
    $this->admin = actuarComoAdminDeTenant();
    $this->falsos['aprobadores']->administradores[$this->admin->id] = $this->admin->name;
});

it('devuelve el plan con acciones, competencias, participantes, totales sin canceladas y botones', function () {
    $plan = planGuardado(['aprobador_id' => $this->admin->id, 'acciones' => [
        cuerpoAccion(['nombre' => 'A', 'costo' => 100, 'duracion_horas' => 10]),
        cuerpoAccion(['nombre' => 'B', 'costo' => 50.5, 'duracion_horas' => 4]),
        cuerpoAccion(['nombre' => 'C', 'costo' => 1000, 'duracion_horas' => 40]),
    ]], actorId: $this->admin->id);
    PlanAccionModel::query()->whereKey($plan['acciones'][2]['id'])->update(['cancelada' => true, 'motivo_cancelacion' => 'Sin cupo']);

    $this->getJson('/api/planes/'.$plan['id'])->assertOk()
        ->assertJsonPath('data.codigo', 'PLAN-0001')
        ->assertJsonPath('data.cantidad_acciones', 2)
        ->assertJsonPath('data.costo_total', 150.5)
        ->assertJsonPath('data.horas_totales', 14)
        ->assertJsonPath('data.acciones.2.estado_accion', 'Cancelada')
        ->assertJsonPath('data.acciones.0.competencias.0.nombre', 'Competencia 1')
        ->assertJsonPath('data.acciones.0.participantes.1.nombre', 'Colaborador 11')
        ->assertJsonPath('data.creado_por.id', $this->admin->id)
        ->assertJsonPath('data.puede.editar', true)
        ->assertJsonPath('data.puede.aprobar', false);
});

it('avisa de cruces de fechas con acciones de otros planes sin impedir nada', function () {
    $uno = planGuardado(['nombre' => 'Uno']);
    $dos = planGuardado(['nombre' => 'Dos', 'acciones' => [cuerpoAccion(['fecha_inicio' => '2026-03-10', 'fecha_fin' => '2026-03-20', 'colaborador_ids' => [10, 14]])]]);

    $respuesta = $this->getJson('/api/planes/'.$dos['id'])->assertOk();

    expect($respuesta->json('data.avisos_cruce'))->toHaveCount(1)
        ->and($respuesta->json('data.avisos_cruce.0.colaborador_id'))->toBe(10)
        ->and($respuesta->json('data.avisos_cruce.0.con_plan_id'))->toBe($uno['id'])
        ->and($respuesta->json('data.avisos_cruce.0.nombre'))->toBe('Colaborador 10');
});

it('lista los planes sin acciones, con cantidad y costo, filtros y paginación', function () {
    planGuardado(['nombre' => 'Formación técnica', 'anio' => 2026, 'acciones' => [cuerpoAccion(['costo' => 300]), cuerpoAccion(['costo' => 200])]]);
    $dos = planGuardado(['nombre' => 'Liderazgo', 'anio' => 2027, 'acciones' => []]);
    forzarEstadoPlan($dos['id'], 'Aprobado');
    planGuardado(['nombre' => 'Técnico avanzado', 'anio' => 2027, 'acciones' => []]);

    $todos = $this->getJson('/api/planes')->assertOk();
    expect($todos->json('meta.total'))->toBe(3)
        ->and($todos->json('data.0.nombre'))->toBe('Técnico avanzado')
        ->and($todos->json('data.0.acciones'))->toBeNull();

    $this->getJson('/api/planes?anio=2026')->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.cantidad_acciones', 2)->assertJsonPath('data.0.costo_total', 500);
    $this->getJson('/api/planes?estado=Aprobado')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.nombre', 'Liderazgo');
    $this->getJson('/api/planes?q=TÉCNIC')->assertJsonPath('meta.total', 2);
    $this->getJson('/api/planes?per_page=2&page=2')->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 3);
    $this->getJson('/api/planes?estado=Otro')->assertUnprocessable();
});

it('no muestra planes de otro tenant', function () {
    planGuardado();
    actuarComoAdminDeTenant(2);

    $this->getJson('/api/planes')->assertOk()->assertJsonPath('meta.total', 0);
    $this->getJson('/api/planes/1')->assertNotFound();
});

it('ofrece aprobadores, tipos y solo competencias activas en las opciones', function () {
    $this->getJson('/api/planes/opciones')->assertOk()
        ->assertJsonCount(6, 'tipos')
        ->assertJsonPath('aprobadores.0.id', $this->admin->id)
        ->assertJsonCount(3, 'competencias');
});

it('busca colaboradores con vínculo vigente', function () {
    $this->getJson('/api/planes/colaboradores?q=ana')->assertOk()
        ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', 10);
    $this->getJson('/api/planes/colaboradores?per_page=2&page=3')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 5);
});

it('lista cursos disponibles con tipo sugerido, horas y competencias', function () {
    $online = CursoModel::factory()->create(['nombre' => 'Curso SQL', 'tipo' => 'Online', 'duracion_minutos' => 90, 'formador' => 'Ing. Vera']);
    CursoModel::factory()->create(['nombre' => 'Taller mixto', 'tipo' => 'Mixto']);
    CursoModel::factory()->archivado()->create(['nombre' => 'Curso viejo']);

    $respuesta = $this->getJson('/api/planes/cursos')->assertOk();

    expect($respuesta->json('data'))->toHaveCount(2);
    $this->getJson('/api/planes/cursos?q=sql')->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $online->id)
        ->assertJsonPath('data.0.tipo_sugerido', 'ELearning')
        ->assertJsonPath('data.0.duracion_horas', 1.5)
        ->assertJsonPath('data.0.formador', 'Ing. Vera');
    expect(collect($respuesta->json('data'))->firstWhere('nombre', 'Taller mixto')['tipo_sugerido'])->toBeNull();
});

it('exige rol de administrador en las consultas', function () {
    $plan = planGuardado();
    actuarComoUsuarioDeTenant();

    foreach (['/api/planes', '/api/planes/'.$plan['id'], '/api/planes/opciones', '/api/planes/colaboradores', '/api/planes/cursos'] as $ruta) {
        $this->getJson($ruta)->assertForbidden();
    }
    $this->app['auth']->forgetGuards();
    $this->getJson('/api/planes')->assertUnauthorized();
});
