<?php

use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PlanAccionParticipanteModel;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    // Hoy = 2026-03-05. El administrador crea los planes; después se actúa según la prueba.
    $this->admin = actuarComoAdminDeTenant();
    [$this->usuario, $this->colaborador] = colaboradorConCuentaDePlan();
    $this->falsos = actividadFalsos(['vigentes' => [$this->colaborador => 'Diego Paredes', 99 => 'Otra persona'], 'administradores' => [$this->admin->id => $this->admin->name]]);
    $this->accion = function (string $nombre, string $inicio, string $fin) {
        [, $accion] = accionEnPlan(['nombre' => $nombre, 'fecha_inicio' => $inicio, 'fecha_fin' => $fin, 'colaborador_ids' => [$this->colaborador, 99], 'capacidad_maxima' => 5], actorId: $this->admin->id);

        return [$accion, actividadDe($accion, actorId: $this->admin->id)['id']];
    };
    $this->miPlan = function () {
        Sanctum::actingAs($this->usuario, ['user']);

        return $this->getJson('/api/planes/mi-plan')->assertOk();
    };
});

it('cuenta como completada solo la acción Finalizada con asistencia Presente', function () {
    [$presente, $aPresente] = ($this->accion)('Finalizada presente', '2026-01-10', '2026-01-11');
    [$ausente, $aAusente] = ($this->accion)('Finalizada ausente', '2026-01-12', '2026-01-13');
    [$justificado, $aJustificado] = ($this->accion)('Finalizada justificado', '2026-01-14', '2026-01-15');
    ($this->accion)('Finalizada pendiente', '2026-01-16', '2026-01-17');
    [$enCurso, $aEnCurso] = ($this->accion)('En curso presente', '2026-03-02', '2026-03-13');
    registroDeAsistencia($aPresente, $this->colaborador, 'Presente', 5);
    registroDeAsistencia($aAusente, $this->colaborador, 'Ausente');
    registroDeAsistencia($aJustificado, $this->colaborador, 'Justificado');
    registroDeAsistencia($aEnCurso, $this->colaborador, 'Presente');

    $r = ($this->miPlan)();

    $completadas = collect($r->json('data.acciones'))->where('completada', true)->pluck('id')->all();
    expect($completadas)->toBe([$presente])
        ->and($r->json('data.acciones_completadas'))->toBe(1)
        ->and($r->json('data.acciones_asignadas'))->toBe(5)
        ->and($r->json('data.porcentaje_completado'))->toBe(20)
        ->and($enCurso)->not->toBeIn($completadas)
        ->and($ausente)->not->toBeIn($completadas)
        ->and($justificado)->not->toBeIn($completadas);
});

it('una acción sin actividad no cuenta como completada', function () {
    accionEnPlan(['nombre' => 'Sin actividad', 'fecha_inicio' => '2026-01-10', 'fecha_fin' => '2026-01-11', 'colaborador_ids' => [$this->colaborador, 99]]);

    ($this->miPlan)()->assertJsonPath('data.acciones_completadas', 0)->assertJsonPath('data.porcentaje_completado', 0);
});

it('rechaza quitar de la acción a un participante con asistencia registrada', function () {
    [$accion, $actividad] = ($this->accion)('Con asistencia', '2026-03-02', '2026-03-13');
    registroDeAsistencia($actividad, $this->colaborador, 'Presente');
    Sanctum::actingAs($this->admin, ['admin']);

    $this->putJson("/api/plan-acciones/{$accion}/participantes", ['colaborador_ids' => [99]])
        ->assertUnprocessable()->assertJsonPath('code', 'participante_con_asistencia');

    expect(PlanAccionParticipanteModel::query()->where('plan_accion_id', $accion)->pluck('colaborador_id')->map(fn ($i) => (int) $i)->sort()->values()->all())
        ->toBe([$this->colaborador, 99]);
});

it('permite quitar a quien no tiene asistencia registrada, aunque otro sí', function () {
    [$accion, $actividad] = ($this->accion)('Con asistencia de otro', '2026-03-02', '2026-03-13');
    registroDeAsistencia($actividad, $this->colaborador, 'Ausente');
    Sanctum::actingAs($this->admin, ['admin']);

    $this->putJson("/api/plan-acciones/{$accion}/participantes", ['colaborador_ids' => [$this->colaborador]])->assertOk();

    expect(PlanAccionParticipanteModel::query()->where('plan_accion_id', $accion)->pluck('colaborador_id')->map(fn ($i) => (int) $i)->all())->toBe([$this->colaborador]);
});

it('sigue permitiendo quitar en acciones sin actividad y guardar sin cambios en las que sí la tienen', function () {
    [, $sinActividad] = accionEnPlan(['colaborador_ids' => [$this->colaborador, 99], 'capacidad_maxima' => 5], actorId: $this->admin->id);
    [$accion] = ($this->accion)('Con actividad', '2026-03-02', '2026-03-13');
    Sanctum::actingAs($this->admin, ['admin']);

    $this->putJson("/api/plan-acciones/{$sinActividad}/participantes", ['colaborador_ids' => [99]])->assertOk();
    $this->putJson("/api/plan-acciones/{$accion}/participantes", ['colaborador_ids' => [$this->colaborador, 99]])->assertOk();
});
