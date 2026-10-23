<?php

use App\Modules\Desarrollo\Domain\Services\CalculadorDeResultadoDePdi;
use App\Modules\Desarrollo\Domain\ValueObjects\ResultadoDeCompromiso as C;
use App\Modules\Desarrollo\Domain\ValueObjects\ResultadoDePdi as P;

it('todos Cerrada ⇒ Brecha cerrada', function () {
    expect((new CalculadorDeResultadoDePdi)->calcular([C::Cerrada]))->toBe(P::BrechaCerrada)
        ->and((new CalculadorDeResultadoDePdi)->calcular([C::Cerrada, C::Cerrada, C::Cerrada]))->toBe(P::BrechaCerrada);
});

it('ninguno Cerrada ⇒ No cerrada, aunque haya Parcial', function () {
    expect((new CalculadorDeResultadoDePdi)->calcular([C::NoCerrada, C::NoCerrada]))->toBe(P::NoCerrada)
        ->and((new CalculadorDeResultadoDePdi)->calcular([C::Parcial, C::NoCerrada]))->toBe(P::NoCerrada)
        ->and((new CalculadorDeResultadoDePdi)->calcular([C::Parcial]))->toBe(P::NoCerrada)
        ->and((new CalculadorDeResultadoDePdi)->calcular([]))->toBe(P::NoCerrada);
});

it('cualquier otra combinación ⇒ Parcial', function () {
    expect((new CalculadorDeResultadoDePdi)->calcular([C::Cerrada, C::Parcial]))->toBe(P::Parcial)
        ->and((new CalculadorDeResultadoDePdi)->calcular([C::Cerrada, C::NoCerrada]))->toBe(P::Parcial)
        ->and((new CalculadorDeResultadoDePdi)->calcular([C::Cerrada, C::Parcial, C::NoCerrada]))->toBe(P::Parcial);
});
