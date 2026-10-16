<?php

use App\Modules\Desarrollo\Domain\Repositories\PlanRepositoryInterface;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoPlan;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoHistorialPlan;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PlanHistorialModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PlanModel;

beforeEach(function () {
    planFalsos();
    $this->admin = actuarComoAdminDeTenant();
    $this->repositorio = app(PlanRepositoryInterface::class);
});

it('aísla las consultas por tenant', function () {
    $plan = planGuardado();

    expect($this->repositorio->obtener(1, $plan['id']))->not->toBeNull()
        ->and($this->repositorio->obtener(2, $plan['id']))->toBeNull()
        ->and($this->repositorio->existeNombre(2, 2026, 'plan técnico 2026'))->toBeFalse()
        ->and($this->repositorio->existeNombre(1, 2026, 'plan técnico 2026'))->toBeTrue();
});

it('cambia el estado solo si sigue siendo el esperado', function () {
    $plan = planGuardado();

    expect($this->repositorio->cambiarEstadoSi(1, $plan['id'], EstadoPlan::Revision, EstadoPlan::Aprobado, null, $this->admin->id))->toBeFalse()
        ->and(PlanModel::query()->find($plan['id'])->estado)->toBe('Borrador')
        ->and($this->repositorio->cambiarEstadoSi(1, $plan['id'], EstadoPlan::Borrador, EstadoPlan::Revision, null, $this->admin->id))->toBeTrue()
        ->and($this->repositorio->cambiarEstadoSi(1, $plan['id'], EstadoPlan::Borrador, EstadoPlan::Revision, null, $this->admin->id))->toBeFalse()
        ->and($this->repositorio->cambiarEstadoSi(2, $plan['id'], EstadoPlan::Revision, EstadoPlan::Aprobado, null, $this->admin->id))->toBeFalse();
});

it('numera los planes de forma secuencial por tenant', function () {
    $uno = planGuardado(['nombre' => 'Uno']);
    $dos = planGuardado(['nombre' => 'Dos']);

    expect([$uno['codigo'], $dos['codigo']])->toBe(['PLAN-0001', 'PLAN-0002']);
});

it('no elimina planes: el modelo lo rechaza', function () {
    $plan = planGuardado();

    expect(fn () => PlanModel::query()->find($plan['id'])->delete())->toThrow(LogicException::class);
    expect(PlanModel::query()->count())->toBe(1);
});

it('el historial solo se agrega: no se modifica ni se elimina', function () {
    $plan = planGuardado();
    $this->repositorio->registrarHistorial($plan['id'], null, TipoHistorialPlan::Estado, 'Borrador', 'Revision', null, $this->admin->id);
    $renglon = PlanHistorialModel::query()->sole();

    expect(fn () => $renglon->update(['motivo' => 'cambiado']))->toThrow(LogicException::class)
        ->and(fn () => $renglon->delete())->toThrow(LogicException::class);
    expect($this->repositorio->historial(1, $plan['id'], 1, 10)['total'])->toBe(1)
        ->and($this->repositorio->historial(2, $plan['id'], 1, 10)['total'])->toBe(0);
});
