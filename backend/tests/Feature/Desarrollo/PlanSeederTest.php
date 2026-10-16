<?php

use App\Modules\Desarrollo\Infrastructure\Adapters\Pruebas\ColaboradoresVigentesDePrueba;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PlanAccionModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PlanModel;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PlanFormacionSeeder;
use Database\Seeders\UsuariosInicialesSeeder;

it('siembra cinco planes, uno por estado, con los seis tipos, una acción cancelada y todos los estados calculados', function () {
    $this->seed(DatabaseSeeder::class);
    planFalsos(['hoy' => now()->toDateString()]);
    actuarComoAdminDeTenant();

    $planes = PlanModel::query()->orderBy('numero')->get();
    expect($planes->pluck('estado')->all())->toBe(['Borrador', 'Revision', 'Aprobado', 'Ejecutando', 'Cerrado'])
        ->and($planes->pluck('codigo')->all())->toBe(['PLAN-0001', 'PLAN-0002', 'PLAN-0003', 'PLAN-0004', 'PLAN-0005']);

    $acciones = PlanAccionModel::query()->get();
    expect($acciones->pluck('tipo')->filter()->unique()->sort()->values()->all())->toBe(['Codesarrollo', 'ELearning', 'Mentoring', 'OJT', 'Presencial', 'Virtual'])
        ->and($acciones->where('cancelada', true))->toHaveCount(1);

    $estados = collect($planes)->flatMap(fn ($p) => $this->getJson('/api/planes/'.$p->id)->assertOk()->json('data.acciones'))->pluck('estado_accion')->unique()->sort()->values()->all();
    expect($estados)->toBe(['Cancelada', 'EnCurso', 'Finalizada', 'Programada']);
});

it('el Borrador sembrado tiene acciones incompletas, como las que crea la DNF, y el plan cerrado solo acciones finalizadas', function () {
    $this->seed(DatabaseSeeder::class);
    planFalsos();
    actuarComoAdminDeTenant();

    $borrador = $this->getJson('/api/planes/'.PlanModel::query()->where('estado', 'Borrador')->value('id'))->json('data');
    expect(collect($borrador['acciones'])->pluck('completa')->unique()->all())->toBe([false])
        ->and($borrador['origen_dnf'])->not->toBeNull();

    $cerrado = $this->getJson('/api/planes/'.PlanModel::query()->where('estado', 'Cerrado')->value('id'))->json('data');
    expect(collect($cerrado['acciones'])->pluck('estado_accion')->unique()->all())->toBe(['Finalizada']);
});

it('es idempotente: sembrar dos veces no duplica los planes', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(PlanFormacionSeeder::class);

    expect(PlanModel::query()->count())->toBe(5);
});

it('no hace nada si faltan los datos de los que depende', function () {
    $this->seed(UsuariosInicialesSeeder::class);
    $this->seed(PlanFormacionSeeder::class);

    expect(PlanModel::query()->count())->toBe(0);
});

it('el adaptador de prueba del entorno e2e considera vigentes a las tres fichas sembradas', function () {
    $this->seed(DatabaseSeeder::class);
    $adaptador = new ColaboradoresVigentesDePrueba;

    $busqueda = $adaptador->buscar(1, 'luis', 1, 10, '2026-01-01');
    expect($busqueda['total'])->toBe(1)->and($busqueda['items'][0]['nombre'])->toBe('Luis Núñez Andrade')
        ->and($adaptador->buscar(1, null, 1, 10, '2026-01-01')['total'])->toBe(3)
        ->and($adaptador->vigentesPorIds(1, [$busqueda['items'][0]['id'], 9999], '2026-01-01'))->toBe([$busqueda['items'][0]['id']]);
});
