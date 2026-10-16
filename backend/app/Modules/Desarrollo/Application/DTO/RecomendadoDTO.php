<?php

namespace App\Modules\Desarrollo\Application\DTO;

final class RecomendadoDTO
{
    public function __construct(
        public readonly int $competenciaId,
        public readonly string $nombre,
        public readonly string $tipo,
        public readonly ?AreaDTO $area,
        public readonly ?string $nivelActual,
        public readonly string $nivelRequerido,
        public readonly int $cursosCount,
        public readonly int $microcontenidosCount,
    ) {}
}
