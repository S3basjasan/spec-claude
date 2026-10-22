<?php

use App\Modules\Desarrollo\Application\Ports\FuenteDeDnfInterface;
use Laravel\Sanctum\Sanctum;

/**
 * Hoy = 2026-04-12. La actividad (2026-03-02 a 2026-03-13) está Finalizada: su encuesta vence hoy y el hito de 30 días
 * vence hoy (ventana hasta el 2026-04-27).
 */
beforeEach(function () {
    actuarComoAdminDeTenant();
    [$this->usuario, $this->colaborador] = colaboradorConCuentaDePlan();
    $this->falsos = actividadFalsos(['hoy' => '2026-04-12', 'vigentes' => [$this->colaborador => 'Diego Paredes', 99 => 'Otra persona']]);
    $this->progreso = function () {
        Sanctum::actingAs($this->usuario, ['user']);

        return $this->getJson('/api/reportes/mi-progreso')->assertOk();
    };
});

it('arma resumen, evolución, certificados, plan de desarrollo y pendientes del propio colaborador', function () {
    $a = actividadConAsistencia([$this->colaborador => 'Presente', 99 => 'Presente']);
    $micro = microDe(['duracion_minutos' => 90]);
    progresoMicro($micro->id, $this->colaborador, 'Completado');
    progresoMicro($micro->id, 99, 'Completado');
    dnfEvaluada('2026-01-10', $this->colaborador, 3, 'ConBrecha', 'D', 'B');
    dnfEvaluada('2026-03-30', $this->colaborador, 3, 'Cumple', 'B', 'B');
    dnfEvaluada('2026-03-30', 99, 4, 'ConBrecha', 'D', 'B');
    accionEnPlan(['nombre' => 'Taller futuro', 'fecha_inicio' => '2026-05-10', 'fecha_fin' => '2026-05-20', 'colaborador_ids' => [$this->colaborador], 'competencia_ids' => [2]]);

    $r = ($this->progreso)();

    expect($r->json('data.resumen'))->toEqual(['formaciones_completadas' => 2, 'competencias_adquiridas' => 1, 'horas_acumuladas' => 17.5, 'certificados' => 1])
        ->and($r->json('data.evolucion_competencias'))->toBe([
            ['competencia_id' => 3, 'nivel_primera' => 'D', 'nivel_actual' => 'B', 'nivel_requerido' => 'B', 'solo_actual' => false, 'competencia' => 'Competencia 3'],
        ])
        ->and($r->json('data.certificados.0'))->toBe([
            'microcontenido_id' => $micro->id, 'titulo' => $micro->titulo, 'fecha' => now()->format('Y-m-d'),
            'descarga' => '/api/microcontenidos/'.$micro->id.'/certificado',
        ])
        ->and($r->json('data.proximas_acciones'))->toHaveCount(1)
        ->and($r->json('data.proximas_acciones.0.nombre'))->toBe('Taller futuro')
        ->and($r->json('data.proximas_acciones.0.competencias'))->toBe(['Competencia 2'])
        ->and($r->json('data.pendientes.encuestas'))->toHaveCount(1)
        ->and($r->json('data.pendientes.encuestas.0.actividad_id'))->toBe($a['actividad'])
        ->and($r->json('data.pendientes.encuestas.0.vence'))->toBe('2026-04-12')
        ->and($r->json('data.pendientes.seguimientos'))->toHaveCount(1)
        ->and($r->json('data.pendientes.seguimientos.0.hito'))->toBe(30)
        ->and($r->json('data.pendientes.seguimientos.0.vence'))->toBe('2026-04-27')
        ->and($r->json('data.fuentes_no_disponibles'))->toBe([]);
});

it('con una sola DNF muestra solo el nivel actual de la competencia', function () {
    dnfEvaluada('2026-03-30', $this->colaborador, 3, 'ConBrecha', 'C', 'B');

    $e = ($this->progreso)()->json('data.evolucion_competencias.0');

    expect($e['solo_actual'])->toBeTrue()->and($e['nivel_primera'])->toBeNull()->and($e['nivel_actual'])->toBe('C')
        ->and(($this->progreso)()->json('data.resumen.competencias_adquiridas'))->toBe(0);
});

it('las encuestas y los hitos ya respondidos o fuera de ventana no aparecen como pendientes', function () {
    $a = actividadConAsistencia([$this->colaborador => 'Presente']);
    encuestaDe($a['actividad'], $this->colaborador, 5);
    seguimientoDe($a['actividad'], $this->colaborador, 30);

    $r = ($this->progreso)();
    expect($r->json('data.pendientes.encuestas'))->toBe([])->and($r->json('data.pendientes.seguimientos'))->toBe([]);

    $this->falsos['reloj']->fecha = '2026-04-13';
    $b = actividadConAsistencia([$this->colaborador => 'Presente']);
    $r = ($this->progreso)();
    expect(array_column($r->json('data.pendientes.encuestas'), 'actividad_id'))->toBe([])
        ->and(array_column($r->json('data.pendientes.seguimientos'), 'actividad_id'))->toBe([$b['actividad']]);
});

it('no ofrece pendientes de actividades en las que no estuvo presente o que no han finalizado', function () {
    actividadConAsistencia([$this->colaborador => 'Ausente']);
    actividadConAsistencia([$this->colaborador => 'Presente'], ['fecha_inicio' => '2026-04-01', 'fecha_fin' => '2026-04-30']);

    $r = ($this->progreso)();

    expect($r->json('data.pendientes.encuestas'))->toBe([])->and($r->json('data.pendientes.seguimientos'))->toBe([]);
});

it('un colaborador sin datos recibe una respuesta vacía, no un error', function () {
    $r = ($this->progreso)();

    expect($r->json('data.resumen'))->toEqual(['formaciones_completadas' => 0, 'competencias_adquiridas' => 0, 'horas_acumuladas' => 0, 'certificados' => 0])
        ->and($r->json('data.evolucion_competencias'))->toBe([])->and($r->json('data.certificados'))->toBe([])
        ->and($r->json('data.proximas_acciones'))->toBe([])->and($r->json('data.pendientes'))->toBe(['encuestas' => [], 'seguimientos' => []]);
});

it('un usuario sin ficha asociada recibe una respuesta vacía', function () {
    actuarComoUsuarioDeTenant();

    $this->getJson('/api/reportes/mi-progreso')->assertOk()->assertJsonPath('data.resumen.formaciones_completadas', 0);
});

it('nunca incluye datos de otro colaborador', function () {
    actividadConAsistencia([99 => 'Presente']);
    progresoMicro(microDe()->id, 99, 'Completado');

    expect(($this->progreso)()->json('data.resumen'))->toEqual(['formaciones_completadas' => 0, 'competencias_adquiridas' => 0, 'horas_acumuladas' => 0, 'certificados' => 0]);
});

it('degrada con una fuente caída: informa cuál y entrega el resto', function () {
    actividadConAsistencia([$this->colaborador => 'Presente']);
    app()->instance(FuenteDeDnfInterface::class, new class implements FuenteDeDnfInterface
    {
        public function evaluaciones(int $tenantId, array $filtros = []): array
        {
            throw new RuntimeException('DNF no disponible');
        }
    });

    $r = ($this->progreso)();

    expect($r->json('data.fuentes_no_disponibles'))->toBe(['dnf'])->and($r->json('data.resumen.formaciones_completadas'))->toBe(1)
        ->and($r->json('data.evolucion_competencias'))->toBe([]);
});

it('el administrador no obtiene la vista del colaborador', function () {
    actuarComoAdminDeTenant();

    $this->getJson('/api/reportes/mi-progreso')->assertOk()->assertJsonPath('data.resumen.formaciones_completadas', 0);
});
