<?php

use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PlanAccionModel;
use App\Modules\Desarrollo\Presentation\Facades\DesarrolloFacade;

beforeEach(function () {
    $this->falsos = planFalsos();
    $this->admin = actuarComoAdminDeTenant();
    $this->ejecutando = planGuardado(['nombre' => 'En ejecución', 'anio' => 2026, 'acciones' => [
        cuerpoAccion(['nombre' => 'A', 'tipo' => 'Virtual', 'costo' => 100, 'duracion_horas' => 10, 'colaborador_ids' => [10, 11]]),
        cuerpoAccion(['nombre' => 'B cancelada', 'tipo' => 'OJT', 'costo' => 50, 'duracion_horas' => 5]),
    ]]);
    forzarEstadoPlan($this->ejecutando['id'], 'Ejecutando');
    PlanAccionModel::query()->whereKey($this->ejecutando['acciones'][1]['id'])->update(['cancelada' => true, 'motivo_cancelacion' => 'x']);
    $this->borrador = planGuardado(['nombre' => 'Borrador 2027', 'anio' => 2027, 'acciones' => []]);
    $this->otroTenant = (function () {
        actuarComoAdminDeTenant(2);

        return planGuardado(['nombre' => 'Otro tenant'], tenantId: 2);
    })();
});

it('entrega a Actividades Formativas las acciones no canceladas de planes Ejecutando con sus participantes', function () {
    $acciones = DesarrolloFacade::getAccionesDePlanesEjecutando(1);

    expect($acciones)->toHaveCount(1)
        ->and($acciones[0]['accion_id'])->toBe($this->ejecutando['acciones'][0]['id'])
        ->and($acciones[0]['plan_id'])->toBe($this->ejecutando['id'])
        ->and($acciones[0]['nombre'])->toBe('A')
        ->and($acciones[0]['tipo'])->toBe('Virtual')
        ->and($acciones[0]['fecha_inicio'])->toBe('2026-03-02')
        ->and($acciones[0]['participantes'])->toBe([10, 11]);
    expect(DesarrolloFacade::getAccionesDePlanesEjecutando(2))->toBe([]);
});

it('entrega a Reportes los planes con acciones, horas, costos y estados, con filtros y por tenant', function () {
    $todos = DesarrolloFacade::getPlanesYAcciones(1);

    expect(collect($todos)->pluck('codigo')->all())->toBe(['PLAN-0001', 'PLAN-0002'])
        ->and($todos[0]['costo_total'])->toBe(100.0)
        ->and($todos[0]['horas_totales'])->toBe(10.0)
        ->and($todos[0]['estado'])->toBe('Ejecutando')
        ->and($todos[0]['acciones'])->toHaveCount(2)
        ->and($todos[0]['acciones'][0])->toMatchArray(['tipo' => 'Virtual', 'horas' => 10.0, 'costo' => 100.0, 'cancelada' => false, 'inscritos' => 2])
        ->and($todos[0]['acciones'][1]['cancelada'])->toBeTrue();

    expect(collect(DesarrolloFacade::getPlanesYAcciones(1, ['anio' => 2027]))->pluck('codigo')->all())->toBe(['PLAN-0002'])
        ->and(collect(DesarrolloFacade::getPlanesYAcciones(1, ['estado' => 'Ejecutando']))->pluck('codigo')->all())->toBe(['PLAN-0001'])
        ->and(collect(DesarrolloFacade::getPlanesYAcciones(1, ['plan_id' => $this->borrador['id']]))->pluck('codigo')->all())->toBe(['PLAN-0002'])
        ->and(collect(DesarrolloFacade::getPlanesYAcciones(2))->pluck('nombre')->all())->not->toContain('En ejecución');
});
