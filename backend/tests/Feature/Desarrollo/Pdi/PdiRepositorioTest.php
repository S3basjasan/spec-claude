<?php

use App\Modules\Desarrollo\Domain\Exceptions\PlanDuplicadoPorTipo;
use App\Modules\Desarrollo\Domain\Exceptions\VersionDePdiDesfasada;
use App\Modules\Desarrollo\Domain\Repositories\RepositorioDePdiInterface;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDePdi;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoDeHistorialPdi;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoDePdi;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PdiHistorialModel;

it('genera códigos PDI-0001 consecutivos por tenant', function () {
    tenantDos();

    expect(pdiEnBd(['colaborador' => 10])->codigo)->toBe('PDI-0001')
        ->and(pdiEnBd(['colaborador' => 11])->codigo)->toBe('PDI-0002')
        ->and(pdiEnBd(['tenant' => 2, 'colaborador' => 10])->codigo)->toBe('PDI-0001');
});

it('guarda el plan con sus compromisos, acciones e hitos', function () {
    $pdi = pdiEnBd(['tipo' => TipoDePdi::Carrera, 'puesto' => 3, 'acciones' => ['Pendiente', 'Cumplida'], 'hitos' => [['2026-09-01'], ['2026-10-01', 'Realizado']]]);

    expect($pdi->id)->not->toBeNull()
        ->and($pdi->puestoObjetivoId)->toBe(3)
        ->and($pdi->compromisos)->toHaveCount(1)
        ->and($pdi->compromisos[0]->acciones)->toHaveCount(2)
        ->and($pdi->compromisos[0]->nivelActual)->toBe('C')
        ->and($pdi->hitos)->toHaveCount(2);
});

it('solo permite un plan no Cerrado por colaborador y tipo, incluso a nivel de base de datos', function () {
    $primero = pdiEnBd(['colaborador' => 10]);

    expect(fn () => pdiEnBd(['colaborador' => 10]))->toThrow(PlanDuplicadoPorTipo::class);

    pdiEnBd(['colaborador' => 10, 'tipo' => TipoDePdi::Carrera]);
    pdiEnBd(['colaborador' => 11]);

    $repo = app(RepositorioDePdiInterface::class);
    $repo->actualizarCabecera($primero->con(['estado' => EstadoDePdi::Cerrado, 'version' => 2]), 1, 1);

    expect(pdiEnBd(['colaborador' => 10])->codigo)->not->toBeNull();
});

it('planAbierto devuelve el plan no Cerrado del colaborador y tipo', function () {
    $repo = app(RepositorioDePdiInterface::class);
    $pdi = pdiEnBd(['colaborador' => 10]);

    expect($repo->planAbierto(1, 10, TipoDePdi::Mejora))->toBe(['id' => $pdi->id, 'codigo' => $pdi->codigo])
        ->and($repo->planAbierto(1, 10, TipoDePdi::Carrera))->toBeNull()
        ->and($repo->planAbierto(1, 11, TipoDePdi::Mejora))->toBeNull();
});

it('reemplaza la estructura de un plan en Borrador', function () {
    $repo = app(RepositorioDePdiInterface::class);
    $pdi = pdiEnBd(['acciones' => ['Pendiente', 'Pendiente'], 'hitos' => [['2026-09-01'], ['2026-10-01']]]);
    $solo = $pdi->con(['compromisos' => [$pdi->compromisos[0]->conAcciones([$pdi->compromisos[0]->acciones[0]])], 'hitos' => []]);

    $recargado = $repo->reemplazarEstructura($solo, 1);

    expect($recargado->compromisos)->toHaveCount(1)
        ->and($recargado->compromisos[0]->acciones)->toHaveCount(1)
        ->and($recargado->hitos)->toBeEmpty();
});

it('actualizar la cabecera con una versión desfasada falla y no cambia nada', function () {
    $repo = app(RepositorioDePdiInterface::class);
    $pdi = pdiEnBd();

    expect(fn () => $repo->actualizarCabecera($pdi->con(['objetivo' => 'Otro', 'version' => 3]), 2, 1))->toThrow(VersionDePdiDesfasada::class);

    $repo->actualizarCabecera($pdi->con(['objetivo' => 'Otro', 'version' => 2]), 1, 1);

    expect($repo->obtener(1, $pdi->id)->objetivo)->toBe('Otro')->and($repo->obtener(1, $pdi->id)->version)->toBe(2);
});

it('el historial es de solo inserción y se lista del más reciente al más antiguo', function () {
    $repo = app(RepositorioDePdiInterface::class);
    $pdi = pdiEnBd();
    $repo->registrarHistorial(1, $pdi->id, TipoDeHistorialPdi::Creacion, null, null, 'Borrador', null, 1);
    $repo->registrarHistorial(1, $pdi->id, TipoDeHistorialPdi::Estado, null, 'Borrador', 'Acordado', null, 1);

    $historial = $repo->historial(1, $pdi->id, 1, 10);

    expect($historial['total'])->toBe(3)
        ->and($historial['items'][0]['tipo'])->toBe('Estado')
        ->and($historial['items'][0]['valor_anterior'])->toBe('Borrador');
    expect(fn () => PdiHistorialModel::query()->first()->update(['motivo' => 'x']))->toThrow(LogicException::class)
        ->and(fn () => PdiHistorialModel::query()->first()->delete())->toThrow(LogicException::class);
});

it('aísla los planes por tenant', function () {
    tenantDos();
    $pdi = pdiEnBd(['tenant' => 2]);

    expect(app(RepositorioDePdiInterface::class)->obtener(1, $pdi->id))->toBeNull()
        ->and(app(RepositorioDePdiInterface::class)->obtener(2, $pdi->id))->not->toBeNull();
});
