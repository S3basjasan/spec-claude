<?php

use App\Modules\Desarrollo\Domain\Entities\ParametrosDeAdecuacion;
use App\Modules\Desarrollo\Domain\Exceptions\AdecuacionInmutable;
use App\Modules\Desarrollo\Domain\Repositories\AdecuacionesRepositoryInterface;
use App\Modules\Desarrollo\Domain\ValueObjects\PesosPorDimension;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\AdecuacionDetalleModel;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\AdecuacionModel;

function repositorioDeAdecuaciones(): AdecuacionesRepositoryInterface
{
    return app(AdecuacionesRepositoryInterface::class);
}

it('asigna códigos secuenciales ADE-0001, ADE-0002… por tenant', function () {
    $a = adecuacionEnBd([['objetivo' => 'B', 'valor' => 'C']]);
    $b = adecuacionEnBd([['objetivo' => 'B', 'valor' => 'B']]);
    $otroTenant = adecuacionEnBd([['objetivo' => 'B', 'valor' => 'B']], ['tenant' => 2]);

    expect($a->codigo->valor())->toBe('ADE-0001')
        ->and($b->codigo->valor())->toBe('ADE-0002')
        ->and($otroTenant->codigo->valor())->toBe('ADE-0001');
});

it('guarda cabecera y resultados tal como se calcularon y los lee de vuelta', function () {
    $guardada = adecuacionEnBd([
        ['id' => 1, 'actual' => 'C', 'objetivo' => 'B', 'valor' => 'C', 'tiempo' => 8],
        ['anios' => [5.0, 3.0], 'tiempo' => 14],
        ['id' => 2, 'objetivo' => 'C', 'valor' => 'A'],
    ], ['experiencia' => 5.0, 'observaciones' => 'Sin novedades']);

    $leida = repositorioDeAdecuaciones()->obtener(1, $guardada->id);

    expect($leida->resultados)->toHaveCount(3)
        ->and($leida->preparacion->value)->toBe('NoLista')
        ->and($leida->umbralMeses)->toBe(12)
        ->and($leida->experienciaRequeridaAnios)->toBe(5.0)
        ->and($leida->cantidadDeBrechas())->toBe(2)
        ->and($leida->tiempoMaximoDeCierre())->toBe(14)
        ->and($leida->resultados[0]->diferencia)->toBe(-1.0)
        ->and($leida->resultados[1]->diferencia)->toBe(-2.0)
        ->and($leida->resultados[2]->estado->value)->toBe('Supera');
    expect(repositorioDeAdecuaciones()->obtener(2, $guardada->id))->toBeNull();
});

it('la adecuación vigente es la más reciente por colaborador y puesto objetivo', function () {
    $vieja = adecuacionEnBd([['objetivo' => 'B', 'valor' => 'C']], ['creada' => '2026-01-01 10:00:00']);
    $nueva = adecuacionEnBd([['objetivo' => 'B', 'valor' => 'B']]);
    $otroPuesto = adecuacionEnBd([['objetivo' => 'B', 'valor' => 'B']], ['puesto' => 3]);
    $otroColaborador = adecuacionEnBd([['objetivo' => 'B', 'valor' => 'B']], ['colaborador' => 11]);

    $ids = array_map(fn ($a) => $a->id, repositorioDeAdecuaciones()->vigentes(1));

    expect($ids)->toContain($nueva->id, $otroPuesto->id, $otroColaborador->id)->not->toContain($vieja->id);

    $lista = repositorioDeAdecuaciones()->listar(1, ['colaborador_id' => 10, 'puesto_objetivo_id' => 2], 1, 15);
    expect($lista['total'])->toBe(2)
        ->and(array_column($lista['items'], 'es_vigente'))->toBe([true, false]);
});

it('no permite actualizar ni eliminar una adecuación ni sus resultados', function () {
    $a = adecuacionEnBd([['objetivo' => 'B', 'valor' => 'C']]);

    expect(fn () => AdecuacionModel::query()->find($a->id)->update(['observaciones' => 'x']))->toThrow(AdecuacionInmutable::class);
    expect(fn () => AdecuacionModel::query()->find($a->id)->delete())->toThrow(AdecuacionInmutable::class);
    expect(fn () => AdecuacionDetalleModel::query()->where('adecuacion_id', $a->id)->first()->update(['tiempo_cierre_meses' => 1]))->toThrow(AdecuacionInmutable::class);
    expect(fn () => AdecuacionDetalleModel::query()->where('adecuacion_id', $a->id)->first()->delete())->toThrow(AdecuacionInmutable::class);
});

it('crea parámetros iniciales 40/30/30 y 12 y registra el historial por campo', function () {
    $repo = repositorioDeAdecuaciones();
    $inicial = $repo->obtenerParametros(1);

    expect($inicial->pesos->comoArreglo())->toBe(['Competencia' => 40, 'Conocimiento' => 30, 'Experiencia' => 30])
        ->and($inicial->umbralMeses)->toBe(12);

    $nuevo = new ParametrosDeAdecuacion(new PesosPorDimension(50, 25, 25), 6);
    $repo->guardarParametros(1, $nuevo, [
        ['campo' => 'peso_competencias', 'anterior' => '40', 'nuevo' => '50'],
        ['campo' => 'umbral_meses', 'anterior' => '12', 'nuevo' => '6'],
    ], 7);

    $leido = $repo->obtenerParametros(1);
    $historial = $repo->historialDeParametros(1, 1, 10);
    expect($leido->umbralMeses)->toBe(6)->and($leido->pesos->competencias)->toBe(50)
        ->and($historial['total'])->toBe(2)
        ->and($historial['items'][0])->toMatchArray(['campo' => 'umbral_meses', 'valor_anterior' => '12', 'valor_nuevo' => '6', 'user_id' => 7]);
});

it('aísla por tenant los parámetros y el historial', function () {
    $repo = repositorioDeAdecuaciones();
    $repo->guardarParametros(1, new ParametrosDeAdecuacion(new PesosPorDimension(60, 20, 20), 3), [['campo' => 'umbral_meses', 'anterior' => '12', 'nuevo' => '3']], 7);

    expect($repo->obtenerParametros(2)->umbralMeses)->toBe(12)
        ->and($repo->historialDeParametros(2, 1, 10)['total'])->toBe(0);
});
