<?php

namespace App\Modules\Estructura\Domain\Entities;

use App\Modules\Estructura\Domain\ValueObjects\CompetenciaAsociada;
use App\Modules\Estructura\Domain\ValueObjects\EstadoCompetencia;

final class RegistroComportamientos
{
    /**
     * @param  array<string, array<int, string>>  $comportamientos  Textos por letra (A–D), en orden; vacío en los listados.
     */
    public function __construct(
        public readonly CompetenciaAsociada $competencia,
        public readonly int $version,
        public readonly ?int $creadoPor = null,
        public readonly ?int $modificadoPor = null,
        public readonly array $comportamientos = [],
    ) {}

    /** El registro no tiene estado propio: es el de su competencia. */
    public function estado(): EstadoCompetencia
    {
        return $this->competencia->estado;
    }

    public function competenciaActiva(): bool
    {
        return $this->competencia->estado === EstadoCompetencia::Activo;
    }
}
