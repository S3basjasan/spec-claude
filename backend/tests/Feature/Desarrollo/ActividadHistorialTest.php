<?php

use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->falsos = actividadFalsos();
    $this->admin = actuarComoAdminDeTenant();
    [, $accion] = accionEnPlan(['colaborador_ids' => [10, 11, 12]]);
    $this->id = actividadDe($accion, actorId: $this->admin->id)['id'];
    $this->guardar = fn (int $version, array $registros) => $this->putJson("/api/actividades/{$this->id}/asistencia", cuerpoAsistencia($version, $registros))->assertOk();
});

it('lista los cambios del más reciente al más antiguo con usuario, participante, anterior y nuevo', function () {
    ($this->guardar)(1, [['colaborador_id' => 10, 'asistencia' => 'Presente', 'calificacion' => 4]]);
    ($this->guardar)(2, [['colaborador_id' => 10, 'asistencia' => 'Ausente']]);

    $r = $this->getJson("/api/actividades/{$this->id}/historial")->assertOk();

    expect($r->json('data.0'))->toMatchArray([
        'usuario' => $this->admin->name, 'colaborador_id' => 10, 'participante' => 'Colaborador 10',
        'asistencia_anterior' => 'Presente', 'asistencia_nueva' => 'Ausente', 'calificacion_anterior' => 4, 'calificacion_nueva' => null,
    ])->and($r->json('data.1'))->toMatchArray([
        'asistencia_anterior' => null, 'asistencia_nueva' => 'Presente', 'calificacion_anterior' => null, 'calificacion_nueva' => 4,
    ])->and($r->json('data.0.fecha'))->toBeString()
        ->and($r->json('meta.total'))->toBe(2);
});

it('registra la eliminación automática de la calificación y el regreso a Pendiente', function () {
    ($this->guardar)(1, [['colaborador_id' => 11, 'asistencia' => 'Presente', 'calificacion' => 5]]);
    ($this->guardar)(2, [['colaborador_id' => 11, 'asistencia' => 'Pendiente']]);

    $this->getJson("/api/actividades/{$this->id}/historial")->assertJsonPath('data.0.asistencia_anterior', 'Presente')
        ->assertJsonPath('data.0.asistencia_nueva', null)->assertJsonPath('data.0.calificacion_anterior', 5)->assertJsonPath('data.0.calificacion_nueva', null);
});

it('pagina el historial', function () {
    ($this->guardar)(1, [['colaborador_id' => 10, 'asistencia' => 'Presente'], ['colaborador_id' => 11, 'asistencia' => 'Presente'], ['colaborador_id' => 12, 'asistencia' => 'Presente']]);

    $this->getJson("/api/actividades/{$this->id}/historial?per_page=2&page=2")->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('meta', ['page' => 2, 'per_page' => 2, 'total' => 3, 'last_page' => 2]);
    $this->getJson("/api/actividades/{$this->id}/historial?per_page=500")->assertUnprocessable();
});

it('devuelve vacío sin cambios, 404 si no existe y exige administrador', function () {
    $this->getJson("/api/actividades/{$this->id}/historial")->assertOk()->assertJsonPath('data', []);
    $this->getJson('/api/actividades/9999/historial')->assertNotFound();

    actuarComoAdminDeTenant(2);
    $this->getJson("/api/actividades/{$this->id}/historial")->assertNotFound();
    actuarComoUsuarioDeTenant();
    $this->getJson("/api/actividades/{$this->id}/historial")->assertForbidden();
});

it('el historial no se puede modificar ni borrar', function () {
    foreach (['post', 'put', 'patch', 'delete'] as $metodo) {
        $this->{$metodo.'Json'}("/api/actividades/{$this->id}/historial")->assertStatus(405);
    }
    expect(collect(Route::getRoutes()->getRoutes())->filter(fn ($r) => str_contains($r->uri(), 'actividades') && in_array('DELETE', $r->methods(), true))->count())->toBe(0);
});
