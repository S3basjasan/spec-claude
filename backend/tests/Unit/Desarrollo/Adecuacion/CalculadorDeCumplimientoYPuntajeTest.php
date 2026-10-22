<?php

use App\Modules\Desarrollo\Domain\Services\CalculadorDeCumplimiento;
use App\Modules\Desarrollo\Domain\Services\CalculadorDePuntaje;
use App\Modules\Desarrollo\Domain\Services\CalculadorDeResultado;
use App\Modules\Desarrollo\Domain\ValueObjects\DimensionDeAdecuacion;
use App\Modules\Desarrollo\Domain\ValueObjects\NivelGrado;
use App\Modules\Desarrollo\Domain\ValueObjects\PesosPorDimension;

it('promedia el cumplimiento por dimensión con tope de 1 por requisito', function () {
    $c = new CalculadorDeResultado;
    $resultados = [
        $c->paraGrado(DimensionDeAdecuacion::Competencia, 1, 'A', null, NivelGrado::B, NivelGrado::C), // 2/3
        $c->paraGrado(DimensionDeAdecuacion::Competencia, 2, 'B', null, NivelGrado::C, NivelGrado::A), // tope 1
        $c->paraExperiencia(null, 4.0, 2.0), // 0,5
    ];

    $cumplimientos = (new CalculadorDeCumplimiento)->porDimension($resultados);

    expect($cumplimientos['Competencia'])->toBe((2 / 3 + 1) / 2)
        ->and($cumplimientos['Experiencia'])->toBe(0.5)
        ->and($cumplimientos['Conocimiento'])->toBeNull();
});

it('ignora los requisitos sin evaluación en el promedio', function () {
    $c = new CalculadorDeResultado;
    $resultados = [
        $c->paraGrado(DimensionDeAdecuacion::Competencia, 1, 'A', null, NivelGrado::B, NivelGrado::B),
        $c->paraGrado(DimensionDeAdecuacion::Competencia, 2, 'B', null, NivelGrado::B, null),
    ];

    expect((new CalculadorDeCumplimiento)->porDimension($resultados)['Competencia'])->toBe(1.0);
});

it('calcula el puntaje del ejemplo de la spec con pesos 40/30/30', function () {
    $puntaje = (new CalculadorDePuntaje)->calcular(
        ['Competencia' => 0.75, 'Conocimiento' => 1.0, 'Experiencia' => 0.5],
        new PesosPorDimension(40, 30, 30),
    );

    expect($puntaje)->toBe(75.0);
});

it('excluye las dimensiones no evaluadas y reescala los pesos', function () {
    // Sin conocimientos: 40 y 30 se reescalan a 4/7 y 3/7 → (4/7)*0,75 + (3/7)*0,5 = 0,642857… → 64,3 %.
    $puntaje = (new CalculadorDePuntaje)->calcular(
        ['Competencia' => 0.75, 'Conocimiento' => null, 'Experiencia' => 0.5],
        new PesosPorDimension(40, 30, 30),
    );

    expect($puntaje)->toBe(64.3);
});

it('devuelve nulo si ninguna dimensión se evaluó y reparte por igual si los pesos presentes son 0', function () {
    $calculador = new CalculadorDePuntaje;

    expect($calculador->calcular(['Competencia' => null, 'Conocimiento' => null, 'Experiencia' => null], new PesosPorDimension(40, 30, 30)))->toBeNull()
        ->and($calculador->calcular(['Competencia' => 0.5, 'Conocimiento' => 1.0, 'Experiencia' => null], new PesosPorDimension(0, 0, 100)))->toBe(75.0);
});
