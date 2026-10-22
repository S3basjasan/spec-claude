<?php

use App\Modules\Desarrollo\Domain\Services\CalculadorDePreparacion;
use App\Modules\Desarrollo\Domain\Services\CalculadorDeResultado;
use App\Modules\Desarrollo\Domain\ValueObjects\DimensionDeAdecuacion;
use App\Modules\Desarrollo\Domain\ValueObjects\NivelGrado;
use App\Modules\Desarrollo\Domain\ValueObjects\Preparacion;

function resultadoDePruebaConBrecha(?int $meses)
{
    return (new CalculadorDeResultado)
        ->paraGrado(DimensionDeAdecuacion::Competencia, random_int(1, 999), 'X', null, NivelGrado::B, NivelGrado::D)
        ->conTiempoDeCierre($meses);
}

function resultadoDePruebaQueCumple()
{
    return (new CalculadorDeResultado)->paraGrado(DimensionDeAdecuacion::Competencia, random_int(1, 999), 'Y', null, NivelGrado::B, NivelGrado::B);
}

function resultadoDePruebaSinEvaluar()
{
    return (new CalculadorDeResultado)->paraGrado(DimensionDeAdecuacion::Competencia, random_int(1, 999), 'Z', null, NivelGrado::B, null);
}

it('es Lista cuando no hay brechas', function () {
    expect((new CalculadorDePreparacion)->calcular([resultadoDePruebaQueCumple(), resultadoDePruebaQueCumple()], 12))->toBe(Preparacion::Lista);
});

it('es En desarrollo cuando todas las brechas están dentro del umbral, incluido el límite', function () {
    expect((new CalculadorDePreparacion)->calcular([resultadoDePruebaConBrecha(6), resultadoDePruebaConBrecha(12), resultadoDePruebaQueCumple()], 12))->toBe(Preparacion::EnDesarrollo);
});

it('es No lista cuando al menos una brecha supera el umbral', function () {
    expect((new CalculadorDePreparacion)->calcular([resultadoDePruebaConBrecha(6), resultadoDePruebaConBrecha(13)], 12))->toBe(Preparacion::NoLista);
});

it('es Incompleta cuando algún requisito está sin evaluación, aunque haya brechas', function () {
    expect((new CalculadorDePreparacion)->calcular([resultadoDePruebaConBrecha(30), resultadoDePruebaSinEvaluar()], 12))->toBe(Preparacion::Incompleta);
});

it('respeta el umbral recibido', function () {
    expect((new CalculadorDePreparacion)->calcular([resultadoDePruebaConBrecha(8)], 6))->toBe(Preparacion::NoLista)
        ->and((new CalculadorDePreparacion)->calcular([resultadoDePruebaConBrecha(8)], 12))->toBe(Preparacion::EnDesarrollo);
});
