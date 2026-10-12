<?php

namespace App\Modules\Estructura\Application\DTO;

use App\Modules\Estructura\Domain\Entities\Competencia;

final class CompetenciaDTO
{
    /**
     * @param  array<string, string>  $grados  Descripción por letra A–D; vacío en los listados.
     */
    public function __construct(
        public readonly int $id,
        public readonly string $codigo,
        public readonly string $nombre,
        public readonly string $descripcion,
        public readonly string $tipo,
        public readonly string $estado,
        public readonly int $version,
        public readonly array $grados = [],
    ) {}

    public static function desde(Competencia $competencia): self
    {
        return new self(
            $competencia->id,
            $competencia->codigo,
            $competencia->nombre,
            $competencia->descripcion,
            $competencia->tipo->value,
            $competencia->estado->value,
            $competencia->version,
            $competencia->grados,
        );
    }
}
