<?php

namespace App\Modules\Desarrollo\Domain\ValueObjects;

/** Valor de un indicador comparado con el del período anterior equivalente. Sin valor anterior no hay comparación. */
final class VariacionIndicador
{
    public function __construct(
        public readonly ?float $valor,
        public readonly ?float $anterior,
        public readonly ?float $diferencia,
        public readonly ?float $variacionPct,
    ) {}

    /** @return array{valor: float|null, anterior: float|null, diferencia: float|null, variacion_pct: float|null} */
    public function aArreglo(): array
    {
        return [
            'valor' => $this->valor,
            'anterior' => $this->anterior,
            'diferencia' => $this->diferencia,
            'variacion_pct' => $this->variacionPct,
        ];
    }
}
