<?php

namespace App\Modules\Desarrollo\Domain\Entities;

use App\Modules\Desarrollo\Domain\Exceptions\UmbralInvalido;
use App\Modules\Desarrollo\Domain\ValueObjects\PesosPorDimension;

/** Pesos por dimensión y umbral de preparación (en meses) vigentes. */
final class ParametrosDeAdecuacion
{
    public const UMBRAL_INICIAL = 12;

    public function __construct(
        public readonly PesosPorDimension $pesos,
        public readonly int $umbralMeses,
    ) {
        if ($umbralMeses < 1) {
            throw new UmbralInvalido;
        }
    }

    public static function iniciales(): self
    {
        return new self(new PesosPorDimension(40, 30, 30), self::UMBRAL_INICIAL);
    }
}
