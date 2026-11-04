<?php

use App\Modules\Compensaciones\Domain\Repositories\ParametroRepository;
use App\Modules\Compensaciones\Infrastructure\Persistence\Eloquent\Models\ParametroModuloModel;
use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\Carbon;
use Laravel\Sanctum\Sanctum;

require_once __DIR__.'/../../Support/Compensaciones/ParametrosHelpers.php';

afterEach(fn () => Carbon::setTestNow());

it('deja dos filas en dos cambios sucesivos y no modifica la primera', function () {
    actuarComoAdmin();

    putParametro('plazo_anulacion_dias', 10)->assertOk();
    $primera = ParametroModuloModel::query()->first()->only(['id', 'valor', 'vigente_desde', 'created_by']);
    putParametro('plazo_anulacion_dias', 12, 1)->assertOk();

    expect(ParametroModuloModel::query()->count())->toBe(2)
        ->and(ParametroModuloModel::query()->first()->only(['id', 'valor', 'vigente_desde', 'created_by']))->toBe($primera);
});

it('muestra quién, cuándo, el valor anterior y el nuevo del último cambio', function () {
    Carbon::setTestNow('2026-11-18 20:30:00');
    $gestor = UserModel::factory()->admin()->create(['name' => 'Ana Pérez']);
    Sanctum::actingAs($gestor, ['admin']);

    putParametro('plazo_anulacion_dias', 10)->assertOk();
    $datos = putParametro('plazo_anulacion_dias', 12, 1)->assertOk()->json();

    $ultimo = porClave($datos, 'plazo_anulacion_dias')['ultimo_cambio'];
    expect($ultimo['por'])->toBe('Ana Pérez')
        ->and($ultimo['valor_anterior'])->toBe(10)
        ->and($ultimo['valor_nuevo'])->toBe(12)
        // 20:30 UTC es 15:30 en Guayaquil.
        ->and($ultimo['en'])->toBe('2026-11-18T15:30:00-05:00');
});

it('en el primer cambio de una clave no hay valor anterior', function () {
    actuarComoAdmin();

    $datos = putParametro('umbral_concentracion', 60)->assertOk()->json();

    $ultimo = porClave($datos, 'umbral_concentracion')['ultimo_cambio'];
    expect($ultimo['valor_anterior'])->toBeNull()
        ->and($ultimo['valor_nuevo'])->toBe(60);
});

it('una clave sin cambios tiene ultimo_cambio nulo', function () {
    actuarComoAdmin();
    putParametro('umbral_concentracion', 60)->assertOk();

    $datos = $this->getJson('/api/compensaciones/parametros')->json();

    expect(porClave($datos, 'umbral_clima_unidad')['ultimo_cambio'])->toBeNull();
});

it('guardar sin cambiar nada no inserta filas', function () {
    actuarComoAdmin();

    putParametro('plazo_anulacion_dias', 7)->assertOk();
    putParametro('umbral_concentracion', 50)->assertOk();
    expect(ParametroModuloModel::query()->count())->toBe(0);

    putParametro('plazo_anulacion_dias', 10)->assertOk();
    putParametro('plazo_anulacion_dias', 10, 1)->assertOk();
    expect(ParametroModuloModel::query()->count())->toBe(1);
});

it('el repositorio solo expone lecturas e inserción', function () {
    $metodos = array_map(fn ($m) => $m->getName(), (new ReflectionClass(ParametroRepository::class))->getMethods());

    expect(array_filter($metodos, fn ($m) => preg_match('/^(actualizar|borrar|eliminar|update|delete)/i', $m)))->toBe([]);
});
