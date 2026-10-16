<?php

namespace App\Modules\Desarrollo\Domain\ValueObjects;

final class Cobertura
{
    public function __construct(
        public readonly int $activasTotales,
        public readonly int $activasCubiertas,
        public readonly int $porcentaje,
    ) {}
}
