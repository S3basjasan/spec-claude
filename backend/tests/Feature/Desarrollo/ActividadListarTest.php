<?php

use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PlanAccionModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PlanAccionParticipanteModel;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    // Hoy = 2026-03-05.
    $this->falsos = actividadFalsos();
    $this->admin = actuarComoAdminDeTenant();
    $this->listar = fn (string $consulta = '') => $this->getJson('/api/actividades'.$consulta)->assertOk();
    $this->nombres = fn ($r) => collect($r->json('data'))->pluck('nombre')->all();
});

it('lista las columnas del contrato con el estado calculado', function () {
    [$plan, $accion] = accionEnPlan(['nombre' => 'Liderazgo', 'formador' => 'Dra. Ortiz', 'capacidad_maxima' => 8]);
    actividadDe($accion, 'Sala 3', 1);

    ($this->listar)()->assertJson(['data' => [[
        'codigo' => 'ACT-0001', 'accion_id' => $accion, 'nombre' => 'Liderazgo', 'tipo' => 'Presencial',
        'plan' => ['id' => $plan, 'codigo' => 'PLAN-0001', 'estado' => 'Ejecutando'],
        'fecha_inicio' => '2026-03-02', 'fecha_fin' => '2026-03-13', 'lugar_enlace' => 'Sala 3', 'formador' => 'Dra. Ortiz',
        'departamento' => ['id' => 1, 'nombre' => 'Tecnología'], 'capacidad' => 8, 'inscritos' => 2, 'estado' => 'EnCurso', 'asistencia_pendiente' => false,
    ]], 'meta' => ['page' => 1, 'per_page' => 15, 'total' => 1, 'last_page' => 1]]);
});

it('ordena por fecha de inicio descendente y luego por código', function () {
    foreach ([['A', '2026-02-01', '2026-02-02'], ['B', '2026-04-01', '2026-04-02'], ['C', '2026-04-01', '2026-04-03'], ['D', '2026-03-02', '2026-03-03']] as [$n, $i, $f]) {
        [, $accion] = accionEnPlan(['nombre' => $n, 'fecha_inicio' => $i, 'fecha_fin' => $f]);
        actividadDe($accion);
    }

    expect(($this->nombres)(($this->listar)()))->toBe(['B', 'C', 'D', 'A']);
});

it('por defecto muestra solo el año actual y los filtros de fecha lo reemplazan', function () {
    [, $actual] = accionEnPlan(['nombre' => 'De 2026']);
    [, $anterior] = accionEnPlan(['nombre' => 'De 2025', 'fecha_inicio' => '2025-05-05', 'fecha_fin' => '2025-05-06'], plan: ['anio' => 2025]);
    actividadDe($actual);
    actividadDe($anterior);

    expect(($this->nombres)(($this->listar)()))->toBe(['De 2026'])
        ->and(($this->nombres)(($this->listar)('?fecha_desde=2025-01-01&fecha_hasta=2025-12-31')))->toBe(['De 2025'])
        ->and(($this->nombres)(($this->listar)('?fecha_desde=2025-01-01&fecha_hasta=2026-12-31')))->toBe(['De 2026', 'De 2025']);
});

it('filtra por nombre, tipo, estado, departamento, competencia y plan, y combina filtros', function () {
    [$planA, $a] = accionEnPlan(['nombre' => 'Liderazgo situacional', 'tipo' => 'Presencial', 'competencia_ids' => [1]]);
    [$planB, $b] = accionEnPlan(['nombre' => 'Excel avanzado', 'tipo' => 'Virtual', 'competencia_ids' => [2], 'fecha_inicio' => '2026-04-01', 'fecha_fin' => '2026-04-05']);
    [, $c] = accionEnPlan(['nombre' => 'Mentoring senior', 'tipo' => 'Mentoring', 'competencia_ids' => [1, 3], 'fecha_inicio' => '2026-01-05', 'fecha_fin' => '2026-01-20']);
    actividadDe($a, 'Sala 1', 1);
    actividadDe($b, 'https://meet.ejemplo.edu.ec/x', 2);
    actividadDe($c, 'Oficina', 1);

    expect(($this->nombres)(($this->listar)('?q=LIDERAZGO')))->toBe(['Liderazgo situacional'])
        ->and(($this->nombres)(($this->listar)('?tipo=Virtual')))->toBe(['Excel avanzado'])
        ->and(($this->nombres)(($this->listar)('?estado=EnCurso')))->toBe(['Liderazgo situacional'])
        ->and(($this->nombres)(($this->listar)('?estado=Programada')))->toBe(['Excel avanzado'])
        ->and(($this->nombres)(($this->listar)('?estado=Finalizada')))->toBe(['Mentoring senior'])
        ->and(($this->nombres)(($this->listar)('?departamento_id=1')))->toBe(['Liderazgo situacional', 'Mentoring senior'])
        ->and(($this->nombres)(($this->listar)('?competencia_id=1')))->toBe(['Liderazgo situacional', 'Mentoring senior'])
        ->and(($this->nombres)(($this->listar)('?competencia_id=3')))->toBe(['Mentoring senior'])
        ->and(($this->nombres)(($this->listar)("?plan_id={$planB}")))->toBe(['Excel avanzado'])
        ->and(($this->nombres)(($this->listar)('?fecha_desde=2026-03-01&fecha_hasta=2026-03-31')))->toBe(['Liderazgo situacional'])
        ->and(($this->nombres)(($this->listar)('?departamento_id=1&competencia_id=1&estado=Finalizada')))->toBe(['Mentoring senior'])
        ->and(($this->nombres)(($this->listar)('?q=nada')))->toBe([])
        ->and($planA)->not->toBe($planB);
});

it('una acción cancelada se filtra como Cancelada', function () {
    [, $a] = accionEnPlan(['nombre' => 'Cancelada luego']);
    actividadDe($a);
    PlanAccionModel::query()->whereKey($a)->update(['cancelada' => true]);

    expect(($this->nombres)(($this->listar)('?estado=Cancelada')))->toBe(['Cancelada luego'])
        ->and(($this->nombres)(($this->listar)('?estado=EnCurso')))->toBe([]);
});

it('marca asistencia pendiente solo en actividades Finalizadas con inscritos sin registro', function () {
    [, $conPendientes] = accionEnPlan(['nombre' => 'Finalizada con pendientes', 'fecha_inicio' => '2026-01-05', 'fecha_fin' => '2026-01-06']);
    [, $completa] = accionEnPlan(['nombre' => 'Finalizada completa', 'fecha_inicio' => '2026-01-07', 'fecha_fin' => '2026-01-08']);
    [, $enCurso] = accionEnPlan(['nombre' => 'En curso con pendientes']);
    $a1 = actividadDe($conPendientes)['id'];
    $a2 = actividadDe($completa)['id'];
    actividadDe($enCurso);
    registroDeAsistencia($a1, 10, 'Presente');
    registroDeAsistencia($a2, 10, 'Presente');
    registroDeAsistencia($a2, 11, 'Justificado');

    $r = ($this->listar)();
    $por = collect($r->json('data'))->pluck('asistencia_pendiente', 'nombre')->all();

    expect($por)->toBe(['En curso con pendientes' => false, 'Finalizada completa' => false, 'Finalizada con pendientes' => true]);
});

it('un inscrito retirado sin registro no cuenta como pendiente', function () {
    [, $accion] = accionEnPlan(['fecha_inicio' => '2026-01-05', 'fecha_fin' => '2026-01-06']);
    $a = actividadDe($accion)['id'];
    registroDeAsistencia($a, 10, 'Presente');
    PlanAccionParticipanteModel::query()->where('colaborador_id', 11)->delete();

    ($this->listar)()->assertJsonPath('data.0.asistencia_pendiente', false);
});

it('pagina y devuelve los metadatos', function () {
    foreach (range(1, 5) as $i) {
        [, $accion] = accionEnPlan(['nombre' => "Acción {$i}", 'fecha_inicio' => '2026-03-0'.$i, 'fecha_fin' => '2026-03-1'.$i]);
        actividadDe($accion);
    }

    $r = ($this->listar)('?per_page=2&page=3');

    expect(($this->nombres)($r))->toBe(['Acción 1'])
        ->and($r->json('meta'))->toBe(['page' => 3, 'per_page' => 2, 'total' => 5, 'last_page' => 3]);
});

it('valida los filtros', function (string $consulta) {
    $this->getJson('/api/actividades'.$consulta)->assertUnprocessable();
})->with([
    'tipo inválido' => ['?tipo=Presenciall'],
    'estado inválido' => ['?estado=Terminada'],
    'fecha mal formada' => ['?fecha_desde=05-03-2026'],
    'rango invertido' => ['?fecha_desde=2026-05-01&fecha_hasta=2026-04-01'],
    'página cero' => ['?page=0'],
    'departamento no numérico' => ['?departamento_id=abc'],
]);

it('no mezcla actividades de otro tenant', function () {
    [, $accion] = accionEnPlan();
    actividadDe($accion);
    actuarComoAdminDeTenant(2);

    ($this->listar)()->assertJsonPath('meta.total', 0);
});

it('exige administrador', function () {
    actuarComoUsuarioDeTenant();
    $this->getJson('/api/actividades')->assertForbidden();
    app('auth')->forgetGuards();
    $this->getJson('/api/actividades')->assertUnauthorized();
});

it('el número de consultas no crece con la cantidad de actividades', function () {
    $contar = function () {
        $n = 0;
        DB::listen(function () use (&$n) {
            $n++;
        });
        ($this->listar)('?per_page=50');

        return $n;
    };
    foreach (range(1, 3) as $i) {
        [, $accion] = accionEnPlan(['nombre' => "Base {$i}"]);
        actividadDe($accion, 'Sala', 1);
    }
    $con3 = $contar();
    foreach (range(1, 9) as $i) {
        [, $accion] = accionEnPlan(['nombre' => "Extra {$i}"]);
        actividadDe($accion, 'Sala', 2);
    }
    $con12 = $contar();

    expect($con12)->toBe($con3);
});
