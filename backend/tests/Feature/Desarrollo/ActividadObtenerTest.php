<?php

use App\Modules\Desarrollo\Application\Ports\NombresDeReferenciaInterface;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PlanAccionModel;

beforeEach(function () {
    $this->falsos = actividadFalsos();
    $this->admin = actuarComoAdminDeTenant();
});

it('devuelve el detalle con la acción, competencias, participantes y auditoría', function () {
    [, $accion] = accionEnPlan(['competencia_ids' => [1, 2], 'colaborador_ids' => [10, 11, 12]]);
    $a = actividadDe($accion, 'Sala 3', 2, actorId: $this->admin->id);
    registroDeAsistencia($a['id'], 10, 'Presente', 5, 'Participó activamente');

    $this->getJson("/api/actividades/{$a['id']}")->assertOk()
        ->assertJsonPath('data.codigo', 'ACT-0001')
        ->assertJsonPath('data.formador', 'Ing. Vera')
        ->assertJsonPath('data.horas', 16)
        ->assertJsonPath('data.competencias.1.nombre', 'Competencia 2')
        ->assertJsonPath('data.plan.codigo', 'PLAN-0001')
        ->assertJsonPath('data.puede_editar', true)
        ->assertJsonPath('data.puede_registrar_asistencia', true)
        ->assertJsonPath('data.motivo_bloqueo', null)
        ->assertJsonPath('data.resumen_asistencia', ['presentes' => 1, 'ausentes' => 0, 'justificados' => 0, 'pendientes' => 2, 'calificados' => 1, 'promedio' => 5])
        ->assertJsonPath('data.participantes.0', ['colaborador_id' => 10, 'nombre' => 'Colaborador 10', 'asistencia' => 'Presente', 'calificacion' => 5, 'observaciones' => 'Participó activamente'])
        ->assertJsonPath('data.participantes.1.asistencia', 'Pendiente')
        ->assertJsonPath('data.auditoria.creado_por', $this->admin->name)
        ->assertJsonPath('data.auditoria.modificado_por', $this->admin->name);
});

it('los datos de la acción siempre salen del plan vigente', function () {
    [, $accion] = accionEnPlan();
    $a = actividadDe($accion);
    $this->getJson("/api/actividades/{$a['id']}")->assertJsonPath('data.estado', 'EnCurso')->assertJsonPath('data.nombre', 'Análisis con SQL');

    forzarFechasAccion($accion, '2026-04-01', '2026-04-10');
    PlanAccionModel::query()->whereKey($accion)->update(['nombre' => 'Nombre nuevo', 'formador' => 'Dra. Ortiz']);

    $this->getJson("/api/actividades/{$a['id']}")->assertOk()
        ->assertJsonPath('data.estado', 'Programada')
        ->assertJsonPath('data.nombre', 'Nombre nuevo')
        ->assertJsonPath('data.formador', 'Dra. Ortiz')
        ->assertJsonPath('data.fecha_inicio', '2026-04-01')
        ->assertJsonPath('data.motivo_bloqueo', 'actividad_programada')
        ->assertJsonPath('data.puede_registrar_asistencia', false);
});

it('indica el motivo de bloqueo de una actividad cancelada y de un plan cerrado', function () {
    [$plan, $accion] = accionEnPlan();
    $a = actividadDe($accion);

    PlanAccionModel::query()->whereKey($accion)->update(['cancelada' => true]);
    $this->getJson("/api/actividades/{$a['id']}")->assertJsonPath('data.estado', 'Cancelada')->assertJsonPath('data.motivo_bloqueo', 'actividad_cancelada');

    forzarEstadoPlan($plan, 'Cerrado');
    $this->getJson("/api/actividades/{$a['id']}")->assertJsonPath('data.motivo_bloqueo', 'plan_cerrado')->assertJsonPath('data.puede_editar', false);
});

it('conserva y muestra el nombre de un departamento que pasó a inactivo', function () {
    [, $accion] = accionEnPlan();
    $a = actividadDe($accion, 'Sala 1', 2);
    $this->falsos['departamentos']->inactivos = [2 => 'Talento Humano'];
    $this->falsos['departamentos']->activos = [1 => 'Tecnología'];

    $this->getJson("/api/actividades/{$a['id']}")->assertOk()->assertJsonPath('data.departamento', ['id' => 2, 'nombre' => 'Talento Humano']);
});

it('muestra «No disponible» cuando no se puede resolver un nombre', function () {
    [, $accion] = accionEnPlan();
    $a = actividadDe($accion);
    app()->instance(NombresDeReferenciaInterface::class, new class implements NombresDeReferenciaInterface
    {
        public function colaboradores(int $tenantId, array $ids): array
        {
            throw new RuntimeException('Personal no disponible');
        }

        public function puestos(array $ids): array
        {
            return [];
        }

        public function competencias(array $ids): array
        {
            return [];
        }

        public function unidades(array $ids): array
        {
            return [];
        }
    });

    $this->getJson("/api/actividades/{$a['id']}")->assertOk()
        ->assertJsonPath('data.participantes.0.nombre', 'No disponible')
        ->assertJsonPath('data.competencias.0.nombre', 'No disponible');
});

it('devuelve 404 si no existe, es de otro tenant o el id no es numérico', function () {
    [, $accion] = accionEnPlan();
    $a = actividadDe($accion);

    $this->getJson('/api/actividades/9999')->assertNotFound()->assertJsonPath('code', 'actividad_no_encontrada');
    $this->getJson('/api/actividades/abc')->assertNotFound();
    actuarComoAdminDeTenant(2);
    $this->getJson("/api/actividades/{$a['id']}")->assertNotFound();
});

it('exige administrador para consultar el detalle', function () {
    [, $accion] = accionEnPlan();
    $a = actividadDe($accion);
    actuarComoUsuarioDeTenant();

    $this->getJson("/api/actividades/{$a['id']}")->assertForbidden();
    app('auth')->forgetGuards();
    $this->getJson("/api/actividades/{$a['id']}")->assertUnauthorized();
});
