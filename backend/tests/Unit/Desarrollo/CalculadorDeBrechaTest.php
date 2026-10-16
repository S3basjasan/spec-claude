<?php

use App\Modules\Desarrollo\Domain\Services\CalculadorDeBrecha;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoBrecha;
use App\Modules\Desarrollo\Domain\ValueObjects\NivelGrado;
use App\Modules\Desarrollo\Domain\ValueObjects\PrioridadBrecha;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoCompetenciaDnf;

$escala = ['A' => 4, 'B' => 3, 'C' => 2, 'D' => 1];

/**
 * Las 16 combinaciones requerido x actual, con ambos tipos de competencia (SC-003).
 *
 * @return array<string, array{0: string, 1: string, 2: TipoCompetenciaDnf}>
 */
function combinacionesDeBrecha(): array
{
    $casos = [];
    foreach (['A', 'B', 'C', 'D'] as $requerido) {
        foreach (['A', 'B', 'C', 'D'] as $actual) {
            foreach (TipoCompetenciaDnf::cases() as $tipo) {
                $casos["req {$requerido} / actual {$actual} / {$tipo->value}"] = [$requerido, $actual, $tipo];
            }
        }
    }

    return $casos;
}

it('calcula diferencia, porcentaje, prioridad y estado en las 16 combinaciones', function (string $requerido, string $actual, TipoCompetenciaDnf $tipo) use ($escala) {
    $brecha = (new CalculadorDeBrecha)->calcular(7, $tipo, NivelGrado::from($requerido), NivelGrado::from($actual));

    $dif = $escala[$actual] - $escala[$requerido];
    expect($brecha->diferencia)->toBe($dif)
        ->and($brecha->competenciaId)->toBe(7)
        ->and($brecha->tipo)->toBe($tipo);

    if ($dif < 0) {
        $esperado = round(($escala[$requerido] - $escala[$actual]) / $escala[$requerido] * 100, 2);
        $prioridad = match (true) {
            $dif === -3 => PrioridadBrecha::Critica,
            $dif === -2 => PrioridadBrecha::Alta,
            default => $tipo === TipoCompetenciaDnf::Cardinal ? PrioridadBrecha::Media : PrioridadBrecha::Baja,
        };
        expect($brecha->estado)->toBe(EstadoBrecha::ConBrecha)
            ->and($brecha->brechaPorcentaje)->toBe($esperado)
            ->and($brecha->prioridad)->toBe($prioridad);
    } else {
        expect($brecha->estado)->toBe($dif === 0 ? EstadoBrecha::Cumple : EstadoBrecha::Supera)
            ->and($brecha->brechaPorcentaje)->toBe(0.0)
            ->and($brecha->prioridad)->toBeNull();
    }
})->with(combinacionesDeBrecha());

it('reproduce los ejemplos de la especificación', function () {
    $calculador = new CalculadorDeBrecha;

    $cA = $calculador->calcular(1, TipoCompetenciaDnf::Cardinal, NivelGrado::A, NivelGrado::C);
    expect($cA->diferencia)->toBe(-2)->and($cA->brechaPorcentaje)->toBe(50.0)->and($cA->prioridad)->toBe(PrioridadBrecha::Alta);

    $dA = $calculador->calcular(1, TipoCompetenciaDnf::Especifica, NivelGrado::A, NivelGrado::D);
    expect($dA->diferencia)->toBe(-3)->and($dA->brechaPorcentaje)->toBe(75.0)->and($dA->prioridad)->toBe(PrioridadBrecha::Critica);

    $dC = $calculador->calcular(1, TipoCompetenciaDnf::Cardinal, NivelGrado::C, NivelGrado::D);
    expect($dC->brechaPorcentaje)->toBe(50.0)->and($dC->prioridad)->toBe(PrioridadBrecha::Media);

    $dCe = $calculador->calcular(1, TipoCompetenciaDnf::Especifica, NivelGrado::C, NivelGrado::D);
    expect($dCe->prioridad)->toBe(PrioridadBrecha::Baja);

    $bA = $calculador->calcular(1, TipoCompetenciaDnf::Cardinal, NivelGrado::A, NivelGrado::B);
    expect($bA->brechaPorcentaje)->toBe(25.0);
});

it('redondea el porcentaje a dos decimales', function () {
    $brecha = (new CalculadorDeBrecha)->calcular(1, TipoCompetenciaDnf::Cardinal, NivelGrado::B, NivelGrado::D);

    expect($brecha->diferencia)->toBe(-2)->and($brecha->brechaPorcentaje)->toBe(66.67);
});

it('no calcula diferencia, brecha ni prioridad cuando no hay nivel actual', function (TipoCompetenciaDnf $tipo) {
    $brecha = (new CalculadorDeBrecha)->calcular(3, $tipo, NivelGrado::B, null);

    expect($brecha->estado)->toBe(EstadoBrecha::SinEvaluacion)
        ->and($brecha->nivelActual)->toBeNull()
        ->and($brecha->diferencia)->toBeNull()
        ->and($brecha->brechaPorcentaje)->toBeNull()
        ->and($brecha->prioridad)->toBeNull()
        ->and($brecha->tieneBrecha())->toBeFalse();
})->with(TipoCompetenciaDnf::cases());

it('nunca hay brecha si el nivel requerido es el más bajo', function () {
    foreach (NivelGrado::cases() as $actual) {
        $brecha = (new CalculadorDeBrecha)->calcular(1, TipoCompetenciaDnf::Cardinal, NivelGrado::D, $actual);
        expect($brecha->tieneBrecha())->toBeFalse();
    }
});
