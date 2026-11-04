<?php

use App\Modules\Compensaciones\Infrastructure\Persistence\Eloquent\Models\ParametroModuloModel;
use Carbon\Carbon;

require_once __DIR__.'/../../Support/Compensaciones/ParametrosHelpers.php';

afterEach(fn () => Carbon::setTestNow());

it('acepta los extremos del plazo de anulación y rechaza lo que está fuera del rango o no es entero', function () {
    actuarComoAdmin();

    putParametro('plazo_anulacion_dias', 1)->assertOk();
    putParametro('plazo_anulacion_dias', 30, 1)->assertOk();

    foreach ([0, 31, -1, 2.5, 'diez', '', null] as $invalido) {
        putParametro('plazo_anulacion_dias', $invalido, 2)->assertUnprocessable();
    }

    expect(ParametroModuloModel::query()->count())->toBe(2);
});

it('aplica el plazo de anulación de inmediato', function () {
    Carbon::setTestNow('2026-11-18 12:00:00');
    actuarComoAdmin();

    $datos = putParametro('plazo_anulacion_dias', 10)->assertOk()->json();

    expect(porClave($datos, 'plazo_anulacion_dias')['valor'])->toBe(10)
        ->and(porClave($datos, 'plazo_anulacion_dias')['valor_programado'])->toBeNull()
        ->and(substr((string) ParametroModuloModel::query()->first()->getRawOriginal('vigente_desde'), 0, 10))->toBe('2026-11-18');
});

it('acepta de 1 a 90 días de plazo máximo de un desafío y aplica de inmediato', function () {
    actuarComoAdmin();

    putParametro('plazo_max_desafio_dias', 90)->assertOk();
    putParametro('plazo_max_desafio_dias', 91, 1)->assertUnprocessable();
    putParametro('plazo_max_desafio_dias', 0, 1)->assertUnprocessable();

    $datos = $this->getJson('/api/compensaciones/parametros')->json();
    expect(porClave($datos, 'plazo_max_desafio_dias')['valor'])->toBe(90);
});

it('no guarda ningún cambio si uno de la lista es inválido', function () {
    actuarComoAdmin();

    $this->putJson('/api/compensaciones/parametros', ['cambios' => [
        ['clave' => 'plazo_anulacion_dias', 'valor' => 10, 'version' => 0],
        ['clave' => 'plazo_max_desafio_dias', 'valor' => 500, 'version' => 0],
    ]])->assertUnprocessable()->assertJsonValidationErrors(['cambios.1.valor']);

    expect(ParametroModuloModel::query()->count())->toBe(0);
});
