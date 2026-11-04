<?php

use App\Modules\Compensaciones\Application\Services\ParametrosDelModulo;
use App\Modules\Compensaciones\Domain\ValueObjects\ClaveDeParametro;
use App\Modules\Compensaciones\Infrastructure\Persistence\Eloquent\Models\CategoriaReconocimientoModel;
use App\Modules\Compensaciones\Infrastructure\Persistence\Eloquent\Models\ParametroModuloModel;
use Carbon\Carbon;

require_once __DIR__.'/../../Support/Compensaciones/ParametrosHelpers.php';

afterEach(fn () => Carbon::setTestNow());

it('devuelve los seis valores por defecto cuando no hay filas', function () {
    actuarComoAdmin();

    $datos = $this->getJson('/api/compensaciones/parametros')->assertOk()->json();

    expect(collect($datos['data'])->pluck('valor', 'clave')->all())->toBe([
        'puntos_por_colaborador' => 100,
        'plazo_anulacion_dias' => 7,
        'umbral_concentracion' => 50,
        'plazo_max_desafio_dias' => 30,
        'umbral_clima_unidad' => 33,
        'sensibilidad_radar' => 2,
    ]);
    expect(porClave($datos, 'puntos_por_colaborador'))->toMatchArray(['minimo' => 1, 'maximo' => 1000, 'version' => 0, 'valor_programado' => null, 'ultimo_cambio' => null]);
});

it('usa como valor inicial de los puntos la puntuación de la categoría activa más alta si pasa de 100', function () {
    CategoriaReconocimientoModel::factory()->create(['puntuacion_base' => 150]);
    CategoriaReconocimientoModel::factory()->create(['puntuacion_base' => 900, 'estado' => 'Inactivo']);
    actuarComoAdmin();

    $datos = $this->getJson('/api/compensaciones/parametros')->json();

    expect(porClave($datos, 'puntos_por_colaborador')['valor'])->toBe(150)
        ->and(porClave($datos, 'puntos_por_colaborador')['por_defecto'])->toBe(150);
});

it('rechaza puntos menores que la categoría activa más alta y nombra la categoría', function () {
    CategoriaReconocimientoModel::factory()->create(['nombre' => 'Liderazgo', 'puntuacion_base' => 200]);
    actuarComoAdmin();

    putParametro('puntos_por_colaborador', 150)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['cambios.0.valor' => 'No puede ser menor que la puntuación de la categoría «Liderazgo» (200 puntos).']);

    expect(ParametroModuloModel::query()->count())->toBe(0);
    putParametro('puntos_por_colaborador', 200)->assertOk();
});

it('con empate en la puntuación más alta nombra la categoría de menor id', function () {
    CategoriaReconocimientoModel::factory()->create(['nombre' => 'Primera', 'puntuacion_base' => 300]);
    CategoriaReconocimientoModel::factory()->create(['nombre' => 'Segunda', 'puntuacion_base' => 300]);
    actuarComoAdmin();

    putParametro('puntos_por_colaborador', 250)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['cambios.0.valor' => 'No puede ser menor que la puntuación de la categoría «Primera» (300 puntos).']);
});

it('sin categorías activas no hay piso, solo el rango', function () {
    actuarComoAdmin();

    putParametro('puntos_por_colaborador', 1)->assertOk();
    putParametro('puntos_por_colaborador', 1000, 1)->assertOk();
    putParametro('puntos_por_colaborador', 0, 2)->assertUnprocessable();
    putParametro('puntos_por_colaborador', 1001, 2)->assertUnprocessable();
});

it('aplica el cambio de puntos desde el día 1 del mes siguiente y no cambia el valor de hoy', function () {
    Carbon::setTestNow('2026-11-18 12:00:00');
    actuarComoAdmin();
    $servicio = app(ParametrosDelModulo::class);

    $respuesta = putParametro('puntos_por_colaborador', 150)->assertOk()->json();

    $puntos = porClave($respuesta, 'puntos_por_colaborador');
    expect($puntos['valor'])->toBe(100)
        ->and($puntos['valor_programado'])->toBe(['valor' => 150, 'vigente_desde' => '2026-12-01'])
        ->and($servicio->valorVigente(ClaveDeParametro::PuntosPorColaborador, '2026-11-30'))->toBe(100)
        ->and($servicio->valorVigente(ClaveDeParametro::PuntosPorColaborador, '2026-12-01'))->toBe(150);
});

it('un segundo cambio el mismo mes reemplaza al programado', function () {
    Carbon::setTestNow('2026-11-18 12:00:00');
    actuarComoAdmin();

    putParametro('puntos_por_colaborador', 150);
    $respuesta = putParametro('puntos_por_colaborador', 180, 1)->assertOk()->json();

    expect(porClave($respuesta, 'puntos_por_colaborador')['valor_programado']['valor'])->toBe(180)
        ->and(ParametroModuloModel::query()->count())->toBe(2);
});

it('al llegar el día 1 del mes siguiente el valor programado pasa a ser el vigente', function () {
    Carbon::setTestNow('2026-11-18 12:00:00');
    actuarComoAdmin();
    putParametro('puntos_por_colaborador', 150);

    Carbon::setTestNow('2026-12-01 08:00:00');
    $datos = $this->getJson('/api/compensaciones/parametros')->json();

    expect(porClave($datos, 'puntos_por_colaborador')['valor'])->toBe(150)
        ->and(porClave($datos, 'puntos_por_colaborador')['valor_programado'])->toBeNull();
});

it('calcula hoy y el mes siguiente con la zona de compensaciones.zona_horaria', function () {
    // 2026-12-01 03:00 UTC sigue siendo 30 de noviembre en Guayaquil (UTC-5).
    Carbon::setTestNow('2026-12-01 03:00:00');
    actuarComoAdmin();

    putParametro('puntos_por_colaborador', 120)->assertOk();

    expect(substr((string) ParametroModuloModel::query()->first()->getRawOriginal('vigente_desde'), 0, 10))->toBe('2026-12-01')
        ->and(app(ParametrosDelModulo::class)->hoy())->toBe('2026-11-30');
});

it('si la categoría más alta se desactiva después, el valor guardado no cambia', function () {
    $categoria = CategoriaReconocimientoModel::factory()->create(['puntuacion_base' => 200]);
    actuarComoAdmin();
    putParametro('puntos_por_colaborador', 250)->assertOk();

    $categoria->update(['estado' => 'Inactivo']);

    expect(ParametroModuloModel::query()->count())->toBe(1)
        ->and(ParametroModuloModel::query()->first()->valor)->toBe(250);
});

it('incluye en meta el máximo permitido para la puntuación de las categorías', function (?int $programado, int $esperado) {
    actuarComoAdmin();
    Carbon::setTestNow('2026-11-10 10:00:00');
    if ($programado !== null) {
        putParametro('puntos_por_colaborador', $programado)->assertOk();
    }

    $this->getJson('/api/compensaciones/parametros')
        ->assertOk()
        ->assertJsonPath('meta.puntuacion_maxima_categoria', $esperado)
        ->assertJsonCount(6, 'data');
})->with([
    'sin programado' => [null, 100],
    'programado menor' => [60, 60],
    'programado mayor' => [150, 100],
]);

it('devuelve meta también tras guardar y a quien no es Gestor le responde 403', function () {
    actuarComoAdmin();
    putParametro('puntos_por_colaborador', 60)->assertOk()->assertJsonPath('meta.puntuacion_maxima_categoria', 60);

    actuarComoUsuario();
    $this->getJson('/api/compensaciones/parametros')->assertForbidden();
});
