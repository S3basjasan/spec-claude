<?php

use App\Modules\Desarrollo\Domain\Services\CalculadorDeResultado;
use App\Modules\Desarrollo\Domain\ValueObjects\DimensionDeAdecuacion;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDeResultado;
use App\Modules\Desarrollo\Domain\ValueObjects\NivelGrado;

it('calcula la diferencia en grados y el estado', function (string $valor, string $objetivo, float $diferencia, EstadoDeResultado $estado) {
    $r = (new CalculadorDeResultado)->paraGrado(
        DimensionDeAdecuacion::Competencia, 12, 'Trabajo en equipo', NivelGrado::D, NivelGrado::from($objetivo), NivelGrado::from($valor),
    );

    expect($r->diferencia)->toBe($diferencia)
        ->and($r->estado)->toBe($estado);
})->with([
    'C frente a B es brecha' => ['C', 'B', -1.0, EstadoDeResultado::ConBrecha],
    'D frente a A es brecha de 3' => ['D', 'A', -3.0, EstadoDeResultado::ConBrecha],
    'B frente a B cumple' => ['B', 'B', 0.0, EstadoDeResultado::Cumple],
    'A frente a B supera' => ['A', 'B', 1.0, EstadoDeResultado::Supera],
]);

it('sin valor de la persona no calcula diferencia', function () {
    $r = (new CalculadorDeResultado)->paraGrado(DimensionDeAdecuacion::Competencia, 1, 'X', null, NivelGrado::B, null);

    expect($r->estado)->toBe(EstadoDeResultado::SinEvaluacion)
        ->and($r->diferencia)->toBeNull()
        ->and($r->cumplimientoObjetivo())->toBeNull();
});

it('calcula la diferencia de experiencia en años', function () {
    $calculador = new CalculadorDeResultado;

    $brecha = $calculador->paraExperiencia(null, 5.0, 3.0);
    $cumple = $calculador->paraExperiencia(null, 5.0, 5.0);
    $supera = $calculador->paraExperiencia(null, 5.0, 6.5);
    $sin = $calculador->paraExperiencia(null, 5.0, null);

    expect($brecha->diferencia)->toBe(-2.0)->and($brecha->estado)->toBe(EstadoDeResultado::ConBrecha)
        ->and($cumple->estado)->toBe(EstadoDeResultado::Cumple)
        ->and($supera->diferencia)->toBe(1.5)->and($supera->estado)->toBe(EstadoDeResultado::Supera)
        ->and($sin->estado)->toBe(EstadoDeResultado::SinEvaluacion)
        ->and($brecha->elementoId)->toBeNull()->and($brecha->tipoElemento)->toBeNull();
});

it('una brecha acepta tiempo de cierre y los demás estados no lo admiten', function () {
    $calculador = new CalculadorDeResultado;
    $brecha = $calculador->paraExperiencia(null, 5.0, 3.0)->conTiempoDeCierre(6);

    expect($brecha->tiempoCierreMeses)->toBe(6);
    expect(fn () => $calculador->paraExperiencia(null, 5.0, 5.0)->conTiempoDeCierre(6))->toThrow(InvalidArgumentException::class);
});

it('el cumplimiento tiene tope de 1 y requerido cero cumple', function () {
    $calculador = new CalculadorDeResultado;

    expect($calculador->paraGrado(DimensionDeAdecuacion::Competencia, 1, 'X', null, NivelGrado::C, NivelGrado::A)->cumplimientoObjetivo())->toBe(1.0)
        ->and($calculador->paraGrado(DimensionDeAdecuacion::Competencia, 1, 'X', null, NivelGrado::B, NivelGrado::C)->cumplimientoObjetivo())->toBe(2 / 3)
        ->and($calculador->paraExperiencia(null, 0.0, 0.0)->cumplimientoObjetivo())->toBe(1.0)
        ->and($calculador->paraExperiencia(null, 5.0, 3.0)->cumplimientoObjetivo())->toBe(0.6);
});
