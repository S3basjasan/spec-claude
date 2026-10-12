<?php

namespace App\Modules\Estructura\Application\DTO;

use App\Modules\Estructura\Domain\ValueObjects\CompetenciaAsociada;

final class CompetenciaDisponibleDTO
{
    public function __construct(
        public readonly int $competenciaId,
        public readonly string $codigo,
        public readonly string $nombre,
        public readonly string $descripcion,
        public readonly string $tipo,
    ) {}

    public static function desde(CompetenciaAsociada $competencia): self
    {
        return new self($competencia->id, $competencia->codigo, $competencia->nombre, $competencia->descripcion, $competencia->tipo->value);
    }
}
