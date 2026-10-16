<?php

use App\Modules\Desarrollo\Application\Ports\ColaboradoresEvaluablesInterface;
use App\Modules\Desarrollo\Application\Ports\NivelesActualesInterface;
use App\Modules\Desarrollo\Application\Ports\PlanDeFormacionInterface;
use App\Modules\Desarrollo\Infrastructure\Adapters\ColaboradoresEvaluablesSinDatos;
use App\Modules\Desarrollo\Infrastructure\Adapters\NivelesActualesSinDatos;
use App\Modules\Desarrollo\Infrastructure\Adapters\PlanDeFormacionNoDisponible;
use App\Modules\Desarrollo\Infrastructure\Adapters\Pruebas\ColaboradoresEvaluablesDePrueba;
use App\Modules\Desarrollo\Infrastructure\Adapters\Pruebas\NivelesActualesDePrueba;
use App\Modules\Desarrollo\Infrastructure\Adapters\Pruebas\PlanDeFormacionDePrueba;
use App\Modules\Desarrollo\Infrastructure\Providers\DesarrolloServiceProvider;

it('enlaza los adaptadores provisionales fuera del entorno e2e', function (string $entorno) {
    $original = app()->environment();
    app()->detectEnvironment(fn () => $entorno);
    (new DesarrolloServiceProvider(app()))->register();

    expect(app(ColaboradoresEvaluablesInterface::class))->toBeInstanceOf(ColaboradoresEvaluablesSinDatos::class)
        ->and(app(NivelesActualesInterface::class))->toBeInstanceOf(NivelesActualesSinDatos::class)
        ->and(app(PlanDeFormacionInterface::class))->toBeInstanceOf(PlanDeFormacionNoDisponible::class);

    app()->detectEnvironment(fn () => $original);
})->with(['testing', 'local', 'production']);

it('enlaza los adaptadores de prueba únicamente en el entorno e2e', function () {
    $original = app()->environment();
    app()->detectEnvironment(fn () => 'e2e');
    (new DesarrolloServiceProvider(app()))->register();

    expect(app(ColaboradoresEvaluablesInterface::class))->toBeInstanceOf(ColaboradoresEvaluablesDePrueba::class)
        ->and(app(NivelesActualesInterface::class))->toBeInstanceOf(NivelesActualesDePrueba::class)
        ->and(app(PlanDeFormacionInterface::class))->toBeInstanceOf(PlanDeFormacionDePrueba::class);

    app()->detectEnvironment(fn () => $original);
});
