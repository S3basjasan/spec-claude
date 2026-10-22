<?php

use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PlanAccionModel;
use Laravel\Sanctum\Sanctum;

/**
 * Crea un plan con una acción que incluye al colaborador y su actividad; deja el plan en el estado dado.
 *
 * @return array{0: int, 1: int, 2: int} plan, acción y actividad
 */
function actividadDelColaborador(int $colaborador, string $nombre, string $inicio, string $fin, string $estadoPlan = 'Ejecutando', array $accion = []): array
{
    [$plan, $accionId] = accionEnPlan(['nombre' => $nombre, 'fecha_inicio' => $inicio, 'fecha_fin' => $fin, 'colaborador_ids' => [$colaborador, 99]] + $accion);
    $a = actividadDe($accionId, 'Sala 1');
    forzarEstadoPlan($plan, $estadoPlan);

    return [$plan, $accionId, $a['id']];
}

beforeEach(function () {
    // Hoy = 2026-03-05. El administrador crea todo; después se actúa como el colaborador.
    $this->admin = actuarComoAdminDeTenant();
    [$this->usuario, $this->colaborador] = colaboradorConCuentaDePlan();
    $this->falsos = actividadFalsos(['vigentes' => [$this->colaborador => 'Diego Paredes', 99 => 'Otra persona']]);
    Sanctum::actingAs($this->usuario, ['user']);
    $this->misActividades = fn () => $this->getJson('/api/actividades/mis-actividades')->assertOk();
});

it('separa próximas e historial y los ordena', function () {
    actividadDelColaborador($this->colaborador, 'Programada tardía', '2026-05-01', '2026-05-02');
    actividadDelColaborador($this->colaborador, 'En curso', '2026-03-02', '2026-03-13');
    actividadDelColaborador($this->colaborador, 'Programada cercana', '2026-04-01', '2026-04-02');
    actividadDelColaborador($this->colaborador, 'Finalizada vieja', '2026-01-10', '2026-01-11');
    actividadDelColaborador($this->colaborador, 'Finalizada reciente', '2026-02-10', '2026-02-11');
    [, $accionCancelada] = actividadDelColaborador($this->colaborador, 'Cancelada', '2026-03-20', '2026-03-21');
    PlanAccionModel::query()->whereKey($accionCancelada)->update(['cancelada' => true]);

    $r = ($this->misActividades)();

    expect(collect($r->json('data.proximas'))->pluck('nombre')->all())->toBe(['En curso', 'Programada cercana', 'Programada tardía'])
        ->and(collect($r->json('data.historial'))->pluck('nombre')->all())->toBe(['Cancelada', 'Finalizada reciente', 'Finalizada vieja'])
        ->and($r->json('data.proximas.0.estado'))->toBe('EnCurso')
        ->and($r->json('data.historial.0.estado'))->toBe('Cancelada');
});

it('muestra tipo, fechas, lugar, formador, asistencia y calificación de la actividad', function () {
    [, , $actividad] = actividadDelColaborador($this->colaborador, 'Liderazgo', '2026-02-10', '2026-02-11');
    registroDeAsistencia($actividad, $this->colaborador, 'Presente', 4, 'Nota interna del formador');

    ($this->misActividades)()->assertJsonPath('data.historial.0', [
        'id' => $actividad, 'codigo' => 'ACT-0001', 'nombre' => 'Liderazgo', 'tipo' => 'Presencial',
        'fecha_inicio' => '2026-02-10', 'fecha_fin' => '2026-02-11', 'lugar_enlace' => 'Sala 1', 'formador' => 'Ing. Vera',
        'estado' => 'Finalizada', 'asistencia' => 'Presente', 'calificacion' => 4,
    ]);
});

it('muestra la asistencia Pendiente tal cual y sin calificación cuando no se registró', function () {
    actividadDelColaborador($this->colaborador, 'Sin registrar', '2026-02-10', '2026-02-11');

    ($this->misActividades)()
        ->assertJsonPath('data.historial.0.asistencia', 'Pendiente')
        ->assertJsonPath('data.historial.0.calificacion', null);
});

it('nunca incluye las observaciones', function () {
    [, , $actividad] = actividadDelColaborador($this->colaborador, 'Con notas', '2026-03-02', '2026-03-13');
    registroDeAsistencia($actividad, $this->colaborador, 'Presente', 5, 'Confidencial del formador');

    $r = ($this->misActividades)();

    expect($r->getContent())->not->toContain('observaciones')->not->toContain('Confidencial del formador');
});

it('solo muestra acciones con actividad de planes Ejecutando o Cerrado y del propio colaborador', function () {
    actividadDelColaborador($this->colaborador, 'Ejecutando', '2026-03-02', '2026-03-13');
    actividadDelColaborador($this->colaborador, 'Cerrado', '2026-01-10', '2026-01-11', 'Cerrado');
    actividadDelColaborador($this->colaborador, 'Aprobado', '2026-03-02', '2026-03-13', 'Aprobado');
    actividadDelColaborador($this->colaborador, 'Revisión', '2026-03-02', '2026-03-13', 'Revision');
    accionEnPlan(['nombre' => 'Sin actividad', 'colaborador_ids' => [$this->colaborador]]);
    [$plan, $otra] = accionEnPlan(['nombre' => 'De otra persona', 'colaborador_ids' => [99]]);
    actividadDe($otra);

    $r = ($this->misActividades)();

    expect(collect($r->json('data.proximas'))->pluck('nombre')->all())->toBe(['Ejecutando'])
        ->and(collect($r->json('data.historial'))->pluck('nombre')->all())->toBe(['Cerrado']);
});

it('calcula el resumen con inscritas, finalizadas, porcentaje de asistencia y promedio', function () {
    [, , $a1] = actividadDelColaborador($this->colaborador, 'Una', '2026-01-10', '2026-01-11');
    [, , $a2] = actividadDelColaborador($this->colaborador, 'Dos', '2026-02-10', '2026-02-11');
    actividadDelColaborador($this->colaborador, 'Tres', '2026-04-01', '2026-04-02');
    registroDeAsistencia($a1, $this->colaborador, 'Presente', 4);
    registroDeAsistencia($a2, $this->colaborador, 'Ausente');

    ($this->misActividades)()->assertJsonPath('data.resumen', [
        'inscritas' => 3, 'finalizadas' => 2, 'asistencias_registradas' => 2, 'presentes' => 1, 'asistencia_porcentaje' => 50, 'calificadas' => 1, 'calificacion_promedio' => 4,
    ]);
});

it('el resumen no inventa valores cuando no hay datos', function () {
    actividadDelColaborador($this->colaborador, 'Futura', '2026-04-01', '2026-04-02');

    ($this->misActividades)()->assertJsonPath('data.resumen.asistencia_porcentaje', null)->assertJsonPath('data.resumen.calificacion_promedio', null)
        ->assertJsonPath('data.resumen.inscritas', 1)->assertJsonPath('data.resumen.finalizadas', 0);
});

it('responde vacío sin actividades o sin ficha de colaborador', function () {
    ($this->misActividades)()->assertJsonPath('data.proximas', [])->assertJsonPath('data.historial', [])->assertJsonPath('data.resumen.inscritas', 0);

    actuarComoUsuarioDeTenant();
    $this->getJson('/api/actividades/mis-actividades')->assertOk()->assertJsonPath('data.proximas', [])->assertJsonPath('data.historial', []);
});

it('exige sesión y no ofrece las actividades de otro tenant', function () {
    actividadDelColaborador($this->colaborador, 'Visible', '2026-03-02', '2026-03-13');
    app('auth')->forgetGuards();
    $this->getJson('/api/actividades/mis-actividades')->assertUnauthorized();

    [$otroUsuario] = colaboradorConCuentaDePlan(2);
    Sanctum::actingAs($otroUsuario, ['user']);
    $this->getJson('/api/actividades/mis-actividades')->assertOk()->assertJsonPath('data.proximas', []);
});
