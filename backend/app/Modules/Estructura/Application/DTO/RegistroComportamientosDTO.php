<?php

namespace App\Modules\Estructura\Application\DTO;

use App\Modules\Estructura\Domain\Entities\RegistroComportamientos;

final class RegistroComportamientosDTO
{
    /**
     * @param  array<string, array<int, string>>  $comportamientos  Textos por letra A–D; vacío en los listados.
     */
    public function __construct(
        public readonly int $competenciaId,
        public readonly string $codigo,
        public readonly string $nombre,
        public readonly string $descripcion,
        public readonly string $tipo,
        public readonly string $estado,
        public readonly int $version,
        public readonly array $comportamientos = [],
    ) {}

    public static function desde(RegistroComportamientos $registro): self
    {
        $competencia = $registro->competencia;

        return new self(
            $competencia->id,
            $competencia->codigo,
            $competencia->nombre,
            $competencia->descripcion,
            $competencia->tipo->value,
            $competencia->estado->value,
            $registro->version,
            $registro->comportamientos,
        );
    }
}
