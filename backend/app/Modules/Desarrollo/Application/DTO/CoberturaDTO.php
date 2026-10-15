<?php

namespace App\Modules\Desarrollo\Application\DTO;

final class CoberturaDTO
{
    public function __construct(
        public readonly int $activasTotales,
        public readonly int $activasCubiertas,
        public readonly int $porcentaje,
    ) {}
}
