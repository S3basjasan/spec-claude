<?php

namespace App\Modules\Estructura\Application\DTO;

use App\Modules\Estructura\Domain\Entities\RegistroPreguntas;

final class RegistroPreguntasDTO
{
    /**
     * @param  array<int, string>  $preguntas  Las cuatro preguntas en orden (vacío en el listado).
     */
    public function __construct(
        public readonly int $competenciaId,
        public readonly string $codigo,
        public readonly string $nombre,
        public readonly string $descripcion,
        public readonly string $tipo,
        public readonly string $estado,
        public readonly int $version,
        public readonly array $preguntas = [],
    ) {}

    public static function desde(RegistroPreguntas $registro): self
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
            $registro->preguntas,
        );
    }
}
