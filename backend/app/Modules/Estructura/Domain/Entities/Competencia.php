<?php

namespace App\Modules\Estructura\Domain\Entities;

use App\Modules\Estructura\Domain\ValueObjects\EstadoCompetencia;
use App\Modules\Estructura\Domain\ValueObjects\TipoCompetencia;

final class Competencia
{
    /**
     * @param  array<string, string>  $grados  Descripción por letra (A–D); vacío en los listados.
     */
    public function __construct(
        public readonly int $id,
        public readonly string $codigo,
        public readonly string $nombre,
        public readonly string $descripcion,
        public readonly TipoCompetencia $tipo,
        public readonly EstadoCompetencia $estado,
        public readonly int $version,
        public readonly ?int $creadoPor = null,
        public readonly ?int $modificadoPor = null,
        public readonly array $grados = [],
    ) {}

    public function estaActiva(): bool
    {
        return $this->estado === EstadoCompetencia::Activo;
    }
}
