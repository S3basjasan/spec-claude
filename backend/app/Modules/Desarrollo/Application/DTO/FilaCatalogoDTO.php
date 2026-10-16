<?php

namespace App\Modules\Desarrollo\Application\DTO;

final class FilaCatalogoDTO
{
    public function __construct(
        public readonly int $competenciaId,
        public readonly string $codigo,
        public readonly string $nombre,
        public readonly string $tipo,
        public readonly string $estado,
        public readonly ?int $areaFormativaId,
        public readonly ?string $areaFormativaNombre,
        public readonly int $cursosCount,
        public readonly int $microcontenidosCount,
        public readonly bool $cubierta,
        public readonly ?string $nivelActual,
        public readonly bool $incluyeNivel,
    ) {}
}
