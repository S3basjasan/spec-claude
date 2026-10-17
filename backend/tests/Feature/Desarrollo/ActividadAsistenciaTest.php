<?php

use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\ActividadAsistenciaModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\ActividadHistorialModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\ActividadModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PlanAccionModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PlanAccionParticipanteModel;

beforeEach(function () {
    $this->falsos = actividadFalsos();
    $this->admin = actuarComoAdminDeTenant();
    // Tres inscritos (10, 11, 12); la actividad queda En curso (hoy 2026-03-05).
    $this->nueva = function (array $accion = [], string $estadoPlan = 'Ejecutando') {
        [$plan, $accionId] = accionEnPlan($accion + ['colaborador_ids' => [10, 11, 12]], $estadoPlan);
        $a = actividadDe($accionId, actorId: $this->admin->id);

        return [$a['id'], $plan, $accionId];
    };
    $this->guardar = fn (int $id, int $version, array $registros) => $this->putJson("/api/actividades/{$id}/asistencia", cuerpoAsistencia($version, $registros));
});

it('guarda asistencia, calificación y observaciones y sube la versión', function () {
    [$id] = ($this->nueva)();

    ($this->guardar)($id, 1, [
        ['colaborador_id' => 10, 'asistencia' => 'Presente', 'calificacion' => 4, 'observaciones' => 'Buen ritmo'],
        ['colaborador_id' => 11, 'asistencia' => 'Ausente', 'observaciones' => 'No avisó'],
        ['colaborador_id' => 12, 'asistencia' => 'Justificado'],
    ])->assertOk()
        ->assertJsonPath('data.version', 2)
        ->assertJsonPath('data.participantes.0', ['colaborador_id' => 10, 'nombre' => 'Colaborador 10', 'asistencia' => 'Presente', 'calificacion' => 4, 'observaciones' => 'Buen ritmo'])
        ->assertJsonPath('data.participantes.1.asistencia', 'Ausente')
        ->assertJsonPath('data.participantes.1.calificacion', null)
        ->assertJsonPath('data.participantes.2.asistencia', 'Justificado')
        ->assertJsonPath('data.resumen_asistencia.presentes', 1)
        ->assertJsonPath('data.resumen_asistencia.promedio', 4);

    expect(ActividadAsistenciaModel::query()->count())->toBe(3)
        ->and(ActividadAsistenciaModel::query()->where('colaborador_id', 10)->value('registrado_by'))->toBe($this->admin->id)
        ->and(ActividadModel::query()->find($id)->version)->toBe(2)
        ->and(ActividadModel::query()->find($id)->updated_by)->toBe($this->admin->id);
});

it('escribe el historial de cada alta con valor anterior vacío y nuevo', function () {
    [$id] = ($this->nueva)();

    ($this->guardar)($id, 1, [
        ['colaborador_id' => 10, 'asistencia' => 'Presente', 'calificacion' => 4],
        ['colaborador_id' => 11, 'asistencia' => 'Ausente'],
    ])->assertOk();

    $h = ActividadHistorialModel::query()->orderBy('colaborador_id')->get();
    expect($h)->toHaveCount(2)
        ->and([$h[0]->colaborador_id, $h[0]->asistencia_anterior, $h[0]->asistencia_nueva, $h[0]->calificacion_anterior, $h[0]->calificacion_nueva, $h[0]->user_id])
        ->toBe([10, null, 'Presente', null, 4, $this->admin->id])
        ->and([$h[1]->asistencia_anterior, $h[1]->asistencia_nueva, $h[1]->calificacion_nueva])->toBe([null, 'Ausente', null]);
});

it('eliminar la calificación al pasar a Ausente, Justificado o Pendiente y lo deja en el historial', function (string $nueva) {
    [$id] = ($this->nueva)();
    registroDeAsistencia($id, 10, 'Presente', 5, 'Participó');

    ($this->guardar)($id, 1, [['colaborador_id' => 10, 'asistencia' => $nueva]])->assertOk()
        ->assertJsonPath('data.participantes.0.asistencia', $nueva)
        ->assertJsonPath('data.participantes.0.calificacion', null);

    $h = ActividadHistorialModel::query()->latest('id')->firstOrFail();
    expect([$h->asistencia_anterior, $h->asistencia_nueva, $h->calificacion_anterior, $h->calificacion_nueva])
        ->toBe(['Presente', $nueva === 'Pendiente' ? null : $nueva, 5, null]);
})->with(['Ausente', 'Justificado', 'Pendiente']);

it('volver a Pendiente borra la fila con su calificación y observaciones', function () {
    [$id] = ($this->nueva)();
    registroDeAsistencia($id, 10, 'Presente', 3, 'Nota interna');

    ($this->guardar)($id, 1, [['colaborador_id' => 10, 'asistencia' => 'Pendiente']])->assertOk()
        ->assertJsonPath('data.participantes.0', ['colaborador_id' => 10, 'nombre' => 'Colaborador 10', 'asistencia' => 'Pendiente', 'calificacion' => null, 'observaciones' => null]);

    expect(ActividadAsistenciaModel::query()->count())->toBe(0);
});

it('pasar a Ausente conserva las observaciones pero no la calificación', function () {
    [$id] = ($this->nueva)();
    registroDeAsistencia($id, 10, 'Presente', 3, 'Nota interna');

    ($this->guardar)($id, 1, [['colaborador_id' => 10, 'asistencia' => 'Ausente', 'observaciones' => 'Nota interna']])->assertOk()
        ->assertJsonPath('data.participantes.0.observaciones', 'Nota interna')->assertJsonPath('data.participantes.0.calificacion', null);
});

it('solo escribe los registros que cambian y un cambio de observaciones no genera historial', function () {
    [$id] = ($this->nueva)();
    registroDeAsistencia($id, 10, 'Presente', 4, null);
    registroDeAsistencia($id, 11, 'Ausente');

    ($this->guardar)($id, 1, [
        ['colaborador_id' => 10, 'asistencia' => 'Presente', 'calificacion' => 4, 'observaciones' => 'Añadida después'],
        ['colaborador_id' => 11, 'asistencia' => 'Ausente'],
    ])->assertOk()->assertJsonPath('data.participantes.0.observaciones', 'Añadida después');

    expect(ActividadHistorialModel::query()->count())->toBe(0);
});

it('un guardado sin cambios no sube la versión', function () {
    [$id] = ($this->nueva)();
    registroDeAsistencia($id, 10, 'Presente', 4);

    ($this->guardar)($id, 1, [['colaborador_id' => 10, 'asistencia' => 'Presente', 'calificacion' => 4]])->assertOk()->assertJsonPath('data.version', 1);
});

it('marcar todos presentes solo cambia a los pendientes y respeta los registros previos', function () {
    [$id] = ($this->nueva)();
    registroDeAsistencia($id, 11, 'Ausente', null, 'Avisó tarde');
    registroDeAsistencia($id, 12, 'Justificado');

    // El cliente solo envía a los Pendientes (el 10) como Presente.
    ($this->guardar)($id, 1, [['colaborador_id' => 10, 'asistencia' => 'Presente']])->assertOk()
        ->assertJsonPath('data.participantes.0.asistencia', 'Presente')
        ->assertJsonPath('data.participantes.1.asistencia', 'Ausente')
        ->assertJsonPath('data.participantes.1.observaciones', 'Avisó tarde')
        ->assertJsonPath('data.participantes.2.asistencia', 'Justificado');

    expect(ActividadHistorialModel::query()->count())->toBe(1);
});

it('es atómico: un registro inválido no guarda ninguno', function () {
    [$id] = ($this->nueva)();

    ($this->guardar)($id, 1, [
        ['colaborador_id' => 10, 'asistencia' => 'Presente', 'calificacion' => 5],
        ['colaborador_id' => 11, 'asistencia' => 'Ausente', 'calificacion' => 3],
    ])->assertUnprocessable()->assertJsonPath('code', 'registro_invalido')->assertJsonValidationErrors('registros.1.calificacion');

    expect(ActividadAsistenciaModel::query()->count())->toBe(0)
        ->and(ActividadHistorialModel::query()->count())->toBe(0)
        ->and(ActividadModel::query()->find($id)->version)->toBe(1);
});

it('rechaza colaboradores no inscritos o repetidos, calificaciones inválidas y observaciones con Pendiente', function (array $registro, string $campo) {
    [$id] = ($this->nueva)();

    ($this->guardar)($id, 1, [$registro])->assertUnprocessable()->assertJsonValidationErrors($campo);
})->with([
    'no inscrito' => [['colaborador_id' => 99, 'asistencia' => 'Presente'], 'registros.0.colaborador_id'],
    'calificación 6' => [['colaborador_id' => 10, 'asistencia' => 'Presente', 'calificacion' => 6], 'registros.0.calificacion'],
    'calificación decimal' => [['colaborador_id' => 10, 'asistencia' => 'Presente', 'calificacion' => 4.5], 'registros.0.calificacion'],
    'calificación sin presente' => [['colaborador_id' => 10, 'asistencia' => 'Justificado', 'calificacion' => 3], 'registros.0.calificacion'],
    'observaciones con pendiente' => [['colaborador_id' => 10, 'asistencia' => 'Pendiente', 'observaciones' => 'x'], 'registros.0.observaciones'],
    'asistencia inválida' => [['colaborador_id' => 10, 'asistencia' => 'Tarde'], 'registros.0.asistencia'],
]);

it('rechaza colaboradores repetidos y un cuerpo sin registros ni versión', function () {
    [$id] = ($this->nueva)();

    ($this->guardar)($id, 1, [['colaborador_id' => 10, 'asistencia' => 'Presente'], ['colaborador_id' => 10, 'asistencia' => 'Ausente']])
        ->assertUnprocessable()->assertJsonValidationErrors('registros.1.colaborador_id');
    $this->putJson("/api/actividades/{$id}/asistencia", ['registros' => []])->assertUnprocessable();
    $this->putJson("/api/actividades/{$id}/asistencia", ['version' => 1])->assertUnprocessable();
});

it('responde 409 si otro administrador guardó antes y no guarda nada', function () {
    [$id] = ($this->nueva)();
    ($this->guardar)($id, 1, [['colaborador_id' => 10, 'asistencia' => 'Presente']])->assertOk();

    ($this->guardar)($id, 1, [['colaborador_id' => 11, 'asistencia' => 'Ausente']])
        ->assertStatus(409)->assertJsonPath('code', 'version_desactualizada');

    expect(ActividadAsistenciaModel::query()->count())->toBe(1);
});

it('no permite registrar en una actividad Programada ni Cancelada y conserva lo existente', function (string $caso) {
    [$id, , $accion] = ($this->nueva)();
    registroDeAsistencia($id, 10, 'Presente', 4);
    if ($caso === 'Programada') {
        forzarFechasAccion($accion, '2026-04-01', '2026-04-10');
    } else {
        PlanAccionModel::query()->whereKey($accion)->update(['cancelada' => true]);
    }

    ($this->guardar)($id, 1, [['colaborador_id' => 11, 'asistencia' => 'Presente']])
        ->assertUnprocessable()->assertJsonPath('code', 'asistencia_no_registrable');

    expect(ActividadAsistenciaModel::query()->count())->toBe(1)
        ->and(ActividadAsistenciaModel::query()->first()->calificacion)->toBe(4);
})->with(['Programada', 'Cancelada']);

it('permite registrar en una actividad Finalizada', function () {
    [$id, , $accion] = ($this->nueva)();
    forzarFechasAccion($accion, '2026-02-01', '2026-02-10');

    ($this->guardar)($id, 1, [['colaborador_id' => 10, 'asistencia' => 'Presente', 'calificacion' => 5]])->assertOk()
        ->assertJsonPath('data.estado', 'Finalizada')->assertJsonPath('data.asistencia_pendiente', true);
});

it('no permite registrar con el plan Cerrado', function () {
    [$id, $plan] = ($this->nueva)();
    forzarEstadoPlan($plan, 'Cerrado');

    ($this->guardar)($id, 1, [['colaborador_id' => 10, 'asistencia' => 'Presente']])->assertUnprocessable()->assertJsonPath('code', 'plan_cerrado');
    expect(ActividadAsistenciaModel::query()->count())->toBe(0);
});

it('un colaborador inscrito después aparece como Pendiente y uno retirado sin registro deja de listarse', function () {
    [$id, , $accion] = ($this->nueva)();
    PlanAccionParticipanteModel::query()->create(['plan_accion_id' => $accion, 'colaborador_id' => 13, 'created_by' => 1]);
    PlanAccionParticipanteModel::query()->where('colaborador_id', 12)->delete();

    $r = $this->getJson("/api/actividades/{$id}")->assertOk();
    expect(collect($r->json('data.participantes'))->pluck('colaborador_id')->all())->toBe([10, 11, 13])
        ->and($r->json('data.participantes.2.asistencia'))->toBe('Pendiente')
        ->and($r->json('data.resumen_asistencia.pendientes'))->toBe(3);
});

it('exige administrador y devuelve 404 si la actividad no existe', function () {
    [$id] = ($this->nueva)();
    ($this->guardar)(9999, 1, [['colaborador_id' => 10, 'asistencia' => 'Presente']])->assertNotFound();

    actuarComoUsuarioDeTenant();
    ($this->guardar)($id, 1, [['colaborador_id' => 10, 'asistencia' => 'Presente']])->assertForbidden();
    app('auth')->forgetGuards();
    ($this->guardar)($id, 1, [['colaborador_id' => 10, 'asistencia' => 'Presente']])->assertUnauthorized();
});
